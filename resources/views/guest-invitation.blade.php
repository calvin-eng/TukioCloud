<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vivaro Events Invitation</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            padding: 20px 16px 32px;
        }
        .brand {
            text-align: center;
            margin-bottom: 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 22px rgba(17, 24, 39, 0.08);
            padding: 16px;
        }
        .invite-image {
            width: 100%;
            height: auto;
            border-radius: 10px;
            display: block;
        }
        .guest-name {
            margin: 12px 0 0;
            font-size: 14px;
            color: #4b5563;
        }
        .button {
            display: inline-block;
            margin-top: 16px;
            text-decoration: none;
            background: #111827;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .not-found {
            text-align: center;
            color: #4b5563;
            font-size: 15px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="brand">Vivaro Events</div>
        <div class="card">
            @if($notFound ?? false)
                <div class="not-found">
                    <p><strong>Invitation not found.</strong></p>
                    <p>Please check your link or contact the event organizer.</p>
                </div>
            @else
                <img src="{{ $cardDataUri }}" alt="Invitation card" class="invite-image">
                <p class="guest-name">Guest: {{ $guest->name }}</p>
                <a href="{{ $downloadUrl }}" class="button">Download Card</a>
            @endif
        </div>
    </main>
</body>
</html>
