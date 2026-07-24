import Dexie from 'dexie';

const db = new Dexie('TukioCloudDB');
db.version(3).stores({
    guests: 'guest_token, event_id',
});

async function loadGuests(guests) {
    if (!guests || guests.length === 0) {
        console.log('[TukioCheckin] No guests to sync — data is empty');
        return;
    }

    console.log('[TukioCheckin] Syncing', guests.length, 'guests to IndexedDB...');
    await db.transaction('rw', db.guests, async () => {
        await db.guests.clear();
        for (var i = 0; i < guests.length; i++) {
            var g = guests[i];
            await db.guests.put({
                guest_token: g.qr_token,
                guest_id: g.id,
                name: g.name,
                event_id: g.event_id,
                short_code: g.short_code,
                checked_in: !!g.checked_in_at,
                synced: true,
                checked_in_at: g.checked_in_at || null,
            });
            if (g.short_code) {
                await db.guests.put({
                    guest_token: g.short_code,
                    guest_id: g.id,
                    name: g.name,
                    event_id: g.event_id,
                    short_code: g.short_code,
                    checked_in: !!g.checked_in_at,
                    synced: true,
                    checked_in_at: g.checked_in_at || null,
                });
            }
        }
    });

    console.log('[TukioCheckin] Guest sync complete — stored', guests.length, 'guests in IndexedDB');
}

async function updateByGuestId(guestId, updates) {
    var all = await db.guests.toArray();
    for (var i = 0; i < all.length; i++) {
        if (all[i].guest_id === guestId) {
            for (var k in updates) {
                if (updates.hasOwnProperty(k)) {
                    all[i][k] = updates[k];
                }
            }
            await db.guests.put(all[i]);
        }
    }
}

async function processToken(token, eventId) {
    var trimmed = (token || '').trim().toUpperCase();
    if (!trimmed) return { status: 'invalid', name: '', message: 'Please enter a valid code' };

    if (!eventId && window.__CHECKIN_DATA) {
        eventId = window.__CHECKIN_DATA.eventId;
    }

    var guest = await db.guests.get(trimmed);
    if (!guest || (eventId && guest.event_id != eventId)) {
        return { status: 'invalid', name: '', message: 'Invalid guest code' };
    }

    if (guest.checked_in) {
        var time = guest.checked_in_at
            ? new Date(guest.checked_in_at).toLocaleTimeString()
            : 'earlier';
        return {
            status: 'already_checked_in',
            name: guest.name,
            message: 'Already checked in at ' + time,
        };
    }

    var checkedInAt = new Date().toISOString();

    await updateByGuestId(guest.guest_id, {
        checked_in: true,
        synced: false,
        checked_in_at: checkedInAt,
    });
    console.log('[TukioCheckin] processToken — write complete for', guest.name, '(guest_id:', guest.guest_id, ') event_id:', guest.event_id);

    syncWithServer(trimmed, checkedInAt, guest.guest_id, guest.event_id);

    return { status: 'checked_in', name: guest.name, message: 'Welcome! Checked in successfully' };
}

async function syncWithServer(token, checkedInAt, guestId, eventId) {
    if (!eventId && window.__CHECKIN_DATA) {
        eventId = window.__CHECKIN_DATA.eventId;
    }
    var csrf = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        var response = await fetch('/api/checkin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                guest_token: token,
                client_timestamp: checkedInAt,
                event_id: eventId,
            }),
        });

        var data = await response.json();

        if (response.ok && response.status === 201) {
            await updateByGuestId(guestId, {
                synced: true,
                checked_in_at: (data.checkin && data.checkin.checked_in_at) || checkedInAt,
            });
        } else if (response.ok && data.message === 'Already checked in') {
            await updateByGuestId(guestId, {
                synced: true,
                checked_in_at: (data.checkin && data.checkin.checked_in_at) || checkedInAt,
            });
        } else if (response.status === 404) {
            await updateByGuestId(guestId, {
                checked_in: false,
                synced: true,
                checked_in_at: null,
            });
        } else {
            scheduleRetry();
        }
    } catch (e) {
        scheduleRetry();
    }
}

var retryTimer = null;

function registerBackgroundSync() {
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        navigator.serviceWorker.ready.then(function (reg) {
            reg.sync.register('sync-checkins').catch(function () {});
        });
    }
}

function scheduleRetry() {
    if (retryTimer) return;
    registerBackgroundSync();
    retryTimer = setTimeout(function () {
        retryTimer = null;
        if (navigator.onLine) {
            retryUnsynced();
        }
    }, 30000);
}

async function retryUnsynced() {
    if (!navigator.onLine) return;

    var all = await db.guests.toArray();
    var items = all.filter(function (g) { return !g.synced && g.checked_in; });

    var seen = {};
    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        if (seen[item.guest_id]) continue;
        seen[item.guest_id] = true;
        await syncWithServer(item.guest_token, item.checked_in_at, item.guest_id, item.event_id);
    }
}

async function getRecentCheckins(limit) {
    limit = limit || 20;
    var eventId = window.__CHECKIN_DATA && window.__CHECKIN_DATA.eventId;
    var all = await db.guests.toArray();
    var checkedIn = all.filter(function (g) { return g.checked_in; });
    console.log('[TukioCheckin] getRecentCheckins — raw:', checkedIn.length, 'checked-in rows, event_id filter:', eventId,
        checkedIn.map(function (g) { return g.name + '(e' + g.event_id + ',g' + g.guest_id + ')'; }));
    var filtered = eventId ? checkedIn.filter(function (g) { return g.event_id === eventId; }) : checkedIn;
    if (filtered.length !== checkedIn.length) {
        console.log('[TukioCheckin] getRecentCheckins — filtered out', checkedIn.length - filtered.length, 'rows from other events');
    }
    var seen = {};
    for (var i = 0; i < filtered.length; i++) {
        var g = filtered[i];
        var existing = seen[g.guest_id];
        if (!existing || (g.checked_in_at && (!existing.checked_in_at || g.checked_in_at > existing.checked_in_at))) {
            seen[g.guest_id] = g;
        }
    }
    var result = [];
    for (var key in seen) {
        if (seen.hasOwnProperty(key) && seen[key].checked_in_at) {
            result.push(seen[key]);
        }
    }
    result.sort(function (a, b) {
        return new Date(b.checked_in_at) - new Date(a.checked_in_at);
    });
    var sliced = result.slice(0, limit);
    console.log('[TukioCheckin] getRecentCheckins — after dedup:', sliced.length,
        sliced.map(function (g) { return g.name + '(' + g.checked_in_at + ')'; }));
    return sliced;
}

async function getPendingCount() {
    var all = await db.guests.toArray();
    var unsynced = all.filter(function (g) { return !g.synced && g.checked_in; });
    var seen = {};
    var count = 0;
    for (var i = 0; i < unsynced.length; i++) {
        var g = unsynced[i];
        if (!seen[g.guest_id]) {
            seen[g.guest_id] = true;
            count++;
        }
    }
    console.log('[TukioCheckin] getPendingCount —', count, 'unsynced');
    return count;
}

window.TukioCheckin = {
    db: db,
    loadGuests: loadGuests,
    processToken: processToken,
    syncWithServer: syncWithServer,
    retryUnsynced: retryUnsynced,
    getRecentCheckins: getRecentCheckins,
    getPendingCount: getPendingCount,
    scheduleRetry: scheduleRetry,
};

navigator.serviceWorker.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SYNC_CHECKINS') {
        retryUnsynced();
    }
});

if (window._resolveTukioCheckinReady) {
    window._resolveTukioCheckinReady();
}
