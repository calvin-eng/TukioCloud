<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TukioCloud') }}</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="64x64" href="/icon-64x64.png">
    <meta name="theme-color" content="#C9A96E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Vivaro Events">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <livewire:layout.navigation />

        <div class="flex">
            @include('livewire.layout.sidebar')

            <div class="flex-1 min-w-0">
                <!-- Page Heading -->
                @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    <!-- QR scanner library (loaded globally so it survives wire:navigate) -->
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <!-- Alpine component for the check-in page.
             
             PRIMARY path (always works): define checkinApp as a global function.
             Alpine evaluates x-data="checkinApp()" as raw JS and finds it on
             window — no Alpine.data() registration needed.
             
             SECONDARY path: also call Alpine.data() if Alpine is available,
             in case Alpine has its own internal resolution that we don't want
             to conflict with. -->
    <script>
        // --- Primary: global function (survives even if alpine:init never fires) ---
        window.checkinApp = window.checkinApp || function() {
            return {
                mode: 'camera',
                manualCode: '',
                result: {
                    show: false,
                    status: '',
                    name: '',
                    message: ''
                },
                recentCheckins: [],
                pendingCount: 0,
                online: navigator.onLine,
                qrScanner: null,
                scannerRunning: false,
                cameraError: '',

                async init() {
                    console.log('[checkinApp] init() fired');
                    var self = this;
                    this.online = navigator.onLine;
                    window.addEventListener('online', function() {
                        self.online = true;
                        if (window.TukioCheckin) TukioCheckin.retryUnsynced();
                    });
                    window.addEventListener('offline', function() {
                        self.online = false;
                    });

                    if (!window.__CHECKIN_DATA) return;

                    if (window.TukioCheckinReady) {
                        await window.TukioCheckinReady;
                    }

                    if (window.__CHECKIN_DATA && window.__CHECKIN_DATA.guests && window.TukioCheckin) {
                        await TukioCheckin.loadGuests(window.__CHECKIN_DATA.guests);
                    }

                    await this.refreshList();
                    if (this.mode === 'camera') {
                        this.$nextTick(function() {
                            self.startScanner();
                        });
                    }
                },

                async refreshList() {
                    if (!window.TukioCheckin) return;
                    this.recentCheckins = await TukioCheckin.getRecentCheckins();
                    this.pendingCount = await TukioCheckin.getPendingCount();
                    console.log('[checkinApp] refreshList — recentCheckins:', this.recentCheckins.length, 'pendingCount:', this.pendingCount);
                },

                startScanner() {
                    console.log('[checkinApp] startScanner() clicked');
                    this.cameraError = '';
                    if (!window.isSecureContext) {
                        this.cameraError = 'Secure context (HTTPS) required for camera access';
                        return;
                    }
                    if (!navigator.mediaDevices?.getUserMedia) {
                        this.cameraError = 'Camera API not supported in this browser';
                        return;
                    }
                    if (typeof Html5Qrcode === 'undefined') {
                        var self = this;
                        setTimeout(function() {
                            if (typeof Html5Qrcode !== 'undefined') {
                                self.startScanner();
                            } else {
                                self.cameraError = 'QR scanner script failed to load';
                            }
                        }, 1000);
                        return;
                    }
                    this.stopScanner();
                    var self = this;
                    try {
                        this.qrScanner = new Html5Qrcode('qr-reader');
                        this.qrScanner.start({
                                facingMode: 'environment'
                            }, {
                                fps: 10,
                                qrbox: {
                                    width: 250,
                                    height: 250
                                }
                            },
                            function(decodedText) {
                                self.handleResult(decodedText);
                            },
                            function() {}
                        ).then(function() {
                            self.scannerRunning = true;
                        }).catch(function(err) {
                            self.scannerRunning = false;
                            var errName = err ? (err.name || err.toString()) : '';
                            if (errName.indexOf('NotAllowedError') !== -1 || errName.indexOf('Permission') !== -1) {
                                self.cameraError = 'permission denied, check browser settings';
                            } else if (errName.indexOf('NotFoundError') !== -1) {
                                self.cameraError = 'no camera found';
                            } else {
                                self.cameraError = 'could not start camera';
                            }
                            console.warn('[checkinApp] Camera start failed:', err);
                        });
                    } catch (e) {
                        this.scannerRunning = false;
                        this.cameraError = 'could not start camera';
                        console.warn('[checkinApp] Camera start failed:', e);
                    }
                },

                stopScanner() {
                    if (this.qrScanner) {
                        try {
                            this.qrScanner.stop().catch(function() {});
                        } catch (e) {}
                        this.qrScanner = null;
                        this.scannerRunning = false;
                    }
                },

                processManualCode: function() {
                    console.log('[checkinApp] processManualCode() clicked, code:', this.manualCode);
                    if (!this.manualCode.trim()) return;
                    this.handleResult(this.manualCode.trim());
                    this.manualCode = '';
                },

                async handleResult(token) {
                    console.log('[checkinApp] handleResult() token:', token);
                    if (!window.TukioCheckin) return;
                    this.stopScanner();
                    console.log('[checkinApp] handleResult — awaiting processToken...');
                    var res = await TukioCheckin.processToken(token);
                    console.log('[checkinApp] handleResult — processToken returned, result:', res.status, 'for', res.name);
                    this.result = {
                        show: true,
                        status: res.status,
                        name: res.name,
                        message: res.message
                    };
                    var self = this;
                    setTimeout(function() {
                        console.log('[checkinApp] handleResult — 800ms elapsed, calling refreshList...');
                        self.refreshList();
                        if (self.mode === 'camera') self.$nextTick(function() {
                            self.startScanner();
                        });
                    }, 800);
                },

                formatTime: function(iso) {
                    if (!iso) return '';
                    return new Date(iso).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            };
        };

        // --- Secondary: register via Alpine.data() if Alpine is available ---
        function _registerAlpineCheckin() {
            if (window.Alpine && !window._alpineCheckinRegistered) {
                Alpine.data('checkinApp', window.checkinApp);
                window._alpineCheckinRegistered = true;
                console.log('[checkinApp] Alpine.data registration complete');
            }
        }

        if (window.Alpine) {
            _registerAlpineCheckin();
        } else {
            document.addEventListener('alpine:init', _registerAlpineCheckin);
            // Fallback: alpine:init is reliable in Livewire v3, but guard
            // against edge cases where it might not fire.
            window.addEventListener('load', function() {
                if (!window._alpineCheckinRegistered) {
                    console.warn('[checkinApp] alpine:init did not fire — registering via load fallback');
                    _registerAlpineCheckin();
                }
            });
        }
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js').then((reg) => {
                    console.log('SW registered:', reg.scope);
                }).catch((err) => {
                    console.error('SW registration failed:', err);
                });
            });
        } else {
            console.warn('Service Workers not supported in this browser.');
        }
    </script>

    @livewireScripts
</body>

</html>