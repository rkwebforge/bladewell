@props([
    // One of: archive, arrow-down, arrow-left, arrow-right, arrow-up, at-sign, banknote, bell, bell-off, bookmark,
    // calendar, calendar-days, calendar-range, camera, chart-bar, check, chevron-down, chevron-left, chevron-right,
    // chevron-up, chevrons-left, chevrons-right, chevrons-up-down, circle-alert, circle-check, circle-help, circle-x,
    // clipboard, clock, coins, copy, credit-card, database, download, ellipsis, external-link, eye, eye-off, file,
    // file-text, filter, folder, folder-open, gift, globe, grip-vertical, headphones, heart, home, image, inbox, info,
    // key, layout-dashboard, link, list, lock, log-in, log-out, mail, map-pin, megaphone, menu, message-circle, mic,
    // minus, moon, music, package, panel-left, paperclip, pause, pencil, percent, phone, play, plus, printer, receipt,
    // redo, refresh-cw, rotate-ccw, rss, save, scissors, search, send, settings, share, shield-check, shopping-bag,
    // shopping-cart, star, store, sun, tag, thumbs-up, trash, triangle-alert, truck, undo, upload, user, user-plus,
    // users, video, volume-2, wallet, x. Any other name is drawn by Blade Icons (blade-ui-kit/blade-icons) if the app
    // has it, e.g. lucide-rocket or heroicon-o-bolt, and otherwise throws, listing these. To add one of your own, draw
    // it into $icons below.
    'name',
])

{{--
    Bladewell's own icon set, drawn inline so there is no icon package to install. Every icon sits on a
    24px grid with about 3px of padding, and shares one style set on the <svg> below: 1.75px outline,
    round ends and joins, and softly rounded corners (radius 3 to 4 on boxes).

    To add one, draw it in that style and list each shape as [tag, attributes].

    Shapes are rendered through Blade's escaped output rather than as raw markup, so nothing here
    needs {!! !!}.
--}}
@php
    // A tiny dot, for the dots in calendars and the stems of "i" and "!".
    $dot = fn (float $x, float $y): array => ['path', ['d' => "M{$x} {$y}h.01"]];

    $icons = [
        'archive' => [['rect', ['x' => '3.5', 'y' => '4.5', 'width' => '17', 'height' => '5', 'rx' => '1.5']], ['path', ['d' => 'M5 9.5V17a2.5 2.5 0 0 0 2.5 2.5h9A2.5 2.5 0 0 0 19 17V9.5']], ['path', ['d' => 'M10 13h4']]],
        'arrow-down' => [['path', ['d' => 'M12 5v13.5']], ['path', ['d' => 'm6.5 13 5.5 5.5 5.5-5.5']]],
        'arrow-left' => [['path', ['d' => 'M19 12H5.5']], ['path', ['d' => 'M11 6.5 5.5 12l5.5 5.5']]],
        'arrow-right' => [['path', ['d' => 'M5 12h13.5']], ['path', ['d' => 'm13 6.5 5.5 5.5-5.5 5.5']]],
        'arrow-up' => [['path', ['d' => 'M12 19V5.5']], ['path', ['d' => 'M6.5 11 12 5.5l5.5 5.5']]],
        'at-sign' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '3.75']], ['path', ['d' => 'M15.75 8.25v4.5a2.75 2.75 0 0 0 5.5 0V12a9.25 9.25 0 1 0-3.6 7.3']]],
        'banknote' => [['rect', ['x' => '2.5', 'y' => '6', 'width' => '19', 'height' => '12', 'rx' => '3']], ['circle', ['cx' => '12', 'cy' => '12', 'r' => '2.5']], $dot(6, 12), $dot(18, 12)],
        'bell' => [['path', ['d' => 'M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15Z']], ['path', ['d' => 'M10.25 21a2 2 0 0 0 3.5 0']], ['path', ['d' => 'M12 3v2']]],
        'bell-off' => [['path', ['d' => 'M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15Z']], ['path', ['d' => 'M10.25 21a2 2 0 0 0 3.5 0']], ['path', ['d' => 'M12 3v2']], ['path', ['d' => 'm4 4 16 16']]],
        'bookmark' => [['path', ['d' => 'M6.5 6a2.5 2.5 0 0 1 2.5-2.5h6A2.5 2.5 0 0 1 17.5 6v14.5l-5.5-4-5.5 4Z']]],
        'calendar' => [['rect', ['x' => '3.5', 'y' => '5', 'width' => '17', 'height' => '15.5', 'rx' => '4']], ['path', ['d' => 'M3.5 10h17']], ['path', ['d' => 'M8 3v4']], ['path', ['d' => 'M16 3v4']]],
        'calendar-days' => [['rect', ['x' => '3.5', 'y' => '5', 'width' => '17', 'height' => '15.5', 'rx' => '4']], ['path', ['d' => 'M3.5 10h17']], ['path', ['d' => 'M8 3v4']], ['path', ['d' => 'M16 3v4']], $dot(8, 14), $dot(12, 14), $dot(16, 14), $dot(8, 17), $dot(12, 17)],
        'calendar-range' => [['rect', ['x' => '3.5', 'y' => '5', 'width' => '17', 'height' => '15.5', 'rx' => '4']], ['path', ['d' => 'M3.5 10h17']], ['path', ['d' => 'M8 3v4']], ['path', ['d' => 'M16 3v4']], ['path', ['d' => 'M8 14h8']], ['path', ['d' => 'M8 17h4']]],
        'camera' => [['path', ['d' => 'M3.5 8.5A2.5 2.5 0 0 1 6 6h2l1.3-2h5.4L16 6h2a2.5 2.5 0 0 1 2.5 2.5V17a2.5 2.5 0 0 1-2.5 2.5H6A2.5 2.5 0 0 1 3.5 17Z']], ['circle', ['cx' => '12', 'cy' => '12.75', 'r' => '3.5']]],
        'chart-bar' => [['path', ['d' => 'M4 4v14.5A1.5 1.5 0 0 0 5.5 20H20']], ['path', ['d' => 'M8.5 16v-4']], ['path', ['d' => 'M12.5 16V8']], ['path', ['d' => 'M16.5 16v-6']]],
        'check' => [['path', ['d' => 'm5 12.5 4.5 4.5L19 7.5']]],
        'chevron-down' => [['path', ['d' => 'm7 10 5 5 5-5']]],
        'chevron-left' => [['path', ['d' => 'm14 7-5 5 5 5']]],
        'chevron-right' => [['path', ['d' => 'm10 7 5 5-5 5']]],
        'chevron-up' => [['path', ['d' => 'm7 14 5-5 5 5']]],
        'chevrons-left' => [['path', ['d' => 'm12.5 7-5 5 5 5']], ['path', ['d' => 'm17.5 7-5 5 5 5']]],
        'chevrons-right' => [['path', ['d' => 'm11.5 7 5 5-5 5']], ['path', ['d' => 'm6.5 7 5 5-5 5']]],
        'chevrons-up-down' => [['path', ['d' => 'm8 9.5 4-4 4 4']], ['path', ['d' => 'm8 14.5 4 4 4-4']]],
        'circle-alert' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'M12 7.75v5']], $dot(12, 16.25)],
        'circle-check' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'm8.5 12.25 2.5 2.5 4.75-5']]],
        'circle-help' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'M9.6 9.4a2.5 2.5 0 0 1 4.85.85c0 1.65-2.45 2.2-2.45 3.75']], $dot(12, 16.5)],
        'circle-x' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'm9.25 9.25 5.5 5.5']], ['path', ['d' => 'm14.75 9.25-5.5 5.5']]],
        'clipboard' => [['rect', ['x' => '8', 'y' => '2.75', 'width' => '8', 'height' => '4', 'rx' => '1']], ['path', ['d' => 'M16 4.75h1.5a2.5 2.5 0 0 1 2.5 2.5V18a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18V7.25a2.5 2.5 0 0 1 2.5-2.5H8']]],
        'clock' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'M12 7.5V12l3 2']]],
        'coins' => [['circle', ['cx' => '9', 'cy' => '9', 'r' => '5.25']], ['path', ['d' => 'M15 9.75a5.25 5.25 0 1 1-5.25 5.25']], ['path', ['d' => 'M8 7.25h1v3.5']]],
        'copy' => [['rect', ['x' => '8.5', 'y' => '8.5', 'width' => '12', 'height' => '12', 'rx' => '3']], ['path', ['d' => 'M15.5 8.5v-2a3 3 0 0 0-3-3h-6a3 3 0 0 0-3 3v6a3 3 0 0 0 3 3h2']]],
        'credit-card' => [['rect', ['x' => '3', 'y' => '5.5', 'width' => '18', 'height' => '13', 'rx' => '3.5']], ['path', ['d' => 'M3 10h18']], ['path', ['d' => 'M7 14.5h3']]],
        'database' => [['ellipse', ['cx' => '12', 'cy' => '6', 'rx' => '7.5', 'ry' => '2.75']], ['path', ['d' => 'M4.5 6v12c0 1.5 3.4 2.75 7.5 2.75s7.5-1.25 7.5-2.75V6']], ['path', ['d' => 'M4.5 12c0 1.5 3.4 2.75 7.5 2.75s7.5-1.25 7.5-2.75']]],
        'download' => [['path', ['d' => 'M12 4v10.5']], ['path', ['d' => 'm7.5 10 4.5 4.5 4.5-4.5']], ['path', ['d' => 'M4.5 15v2.5a2.5 2.5 0 0 0 2.5 2.5h10a2.5 2.5 0 0 0 2.5-2.5V15']]],
        // Dots are tiny circles rather than $dot, which would be too faint to read as a "more" button.
        'ellipsis' => [['circle', ['cx' => '5.5', 'cy' => '12', 'r' => '1']], ['circle', ['cx' => '12', 'cy' => '12', 'r' => '1']], ['circle', ['cx' => '18.5', 'cy' => '12', 'r' => '1']]],
        'external-link' => [['path', ['d' => 'M18.5 13.5v4a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9a3 3 0 0 1 3-3h4']], ['path', ['d' => 'M14.5 3.5h6v6']], ['path', ['d' => 'M20.5 3.5 11.5 12.5']]],
        'eye' => [['path', ['d' => 'M2.75 12C4.8 7.9 8.1 5.75 12 5.75s7.2 2.15 9.25 6.25c-2.05 4.1-5.35 6.25-9.25 6.25S4.8 16.1 2.75 12Z']], ['circle', ['cx' => '12', 'cy' => '12', 'r' => '3']]],
        'eye-off' => [['path', ['d' => 'm4 4 16 16']], ['path', ['d' => 'M9.9 5.97A9.6 9.6 0 0 1 12 5.75c3.9 0 7.2 2.15 9.25 6.25a13.3 13.3 0 0 1-2.2 3.2']], ['path', ['d' => 'M6.6 7.6C5.07 8.7 3.8 10.18 2.75 12c2.05 4.1 5.35 6.25 9.25 6.25 1.6 0 3.08-.36 4.4-1.06']], ['path', ['d' => 'M10 10.1a3 3 0 0 0 3.9 3.9']]],
        'file' => [['path', ['d' => 'M14 3.5H8A2.5 2.5 0 0 0 5.5 6v12A2.5 2.5 0 0 0 8 20.5h8a2.5 2.5 0 0 0 2.5-2.5V8Z']], ['path', ['d' => 'M14 3.5V8h4.5']]],
        'file-text' => [['path', ['d' => 'M14 3.5H8A2.5 2.5 0 0 0 5.5 6v12A2.5 2.5 0 0 0 8 20.5h8a2.5 2.5 0 0 0 2.5-2.5V8Z']], ['path', ['d' => 'M14 3.5V8h4.5']], ['path', ['d' => 'M9 9h1.5']], ['path', ['d' => 'M9 12.5h6']], ['path', ['d' => 'M9 16h6']]],
        'filter' => [['path', ['d' => 'M4.5 4.5h15a.75.75 0 0 1 .58 1.23L14.5 12.5v5.6a1 1 0 0 1-.55.9l-2.5 1.25a1 1 0 0 1-1.45-.9V12.5L3.92 5.73a.75.75 0 0 1 .58-1.23Z']]],
        // App and admin
        'folder' => [['path', ['d' => 'M3.5 7.5A2.5 2.5 0 0 1 6 5h3.4a2 2 0 0 1 1.5.7l1.3 1.5H18a2.5 2.5 0 0 1 2.5 2.5V17a2.5 2.5 0 0 1-2.5 2.5H6A2.5 2.5 0 0 1 3.5 17Z']]],
        'folder-open' => [['path', ['d' => 'M3.5 17V7.5A2.5 2.5 0 0 1 6 5h3.4a2 2 0 0 1 1.5.7l1.3 1.5H17a2.5 2.5 0 0 1 2.5 2.5v.5']], ['path', ['d' => 'M3.5 17l2.2-5.6a2 2 0 0 1 1.85-1.25H20a1 1 0 0 1 .95 1.3l-1.95 6A2.5 2.5 0 0 1 16.6 19.5H6A2.5 2.5 0 0 1 3.5 17Z']]],
        'gift' => [['rect', ['x' => '3.5', 'y' => '8', 'width' => '17', 'height' => '4', 'rx' => '1']], ['path', ['d' => 'M5 12v6a2.5 2.5 0 0 0 2.5 2.5h9A2.5 2.5 0 0 0 19 18v-6']], ['path', ['d' => 'M12 8v12.5']], ['path', ['d' => 'M12 8H8.25a2.25 2.25 0 1 1 0-4.5C11 3.5 12 8 12 8Z']], ['path', ['d' => 'M12 8h3.75a2.25 2.25 0 1 0 0-4.5C13 3.5 12 8 12 8Z']]],
        'globe' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'M3.25 12h17.5']], ['path', ['d' => 'M12 3.25c-2.3 2.4-3.5 5.4-3.5 8.75s1.2 6.35 3.5 8.75']], ['path', ['d' => 'M12 3.25c2.3 2.4 3.5 5.4 3.5 8.75s-1.2 6.35-3.5 8.75']]],
        // Dots are tiny circles, as on ellipsis: a drag handle has to be seen.
        'grip-vertical' => [['circle', ['cx' => '9', 'cy' => '6', 'r' => '1']], ['circle', ['cx' => '9', 'cy' => '12', 'r' => '1']], ['circle', ['cx' => '9', 'cy' => '18', 'r' => '1']], ['circle', ['cx' => '15', 'cy' => '6', 'r' => '1']], ['circle', ['cx' => '15', 'cy' => '12', 'r' => '1']], ['circle', ['cx' => '15', 'cy' => '18', 'r' => '1']]],
        'headphones' => [['path', ['d' => 'M3.5 18v-5a8.5 8.5 0 0 1 17 0v5']], ['path', ['d' => 'M3.5 14.5h2a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5Z']], ['path', ['d' => 'M20.5 14.5h-2a1.5 1.5 0 0 0-1.5 1.5v3a1.5 1.5 0 0 0 1.5 1.5h.5a1.5 1.5 0 0 0 1.5-1.5Z']]],
        'heart' => [['path', ['d' => 'M12 19.5s-7.75-4.5-7.75-10A4.25 4.25 0 0 1 12 7.1a4.25 4.25 0 0 1 7.75 2.4c0 5.5-7.75 10-7.75 10Z']]],
        'home' => [['path', ['d' => 'm3.5 11 8.5-7 8.5 7']], ['path', ['d' => 'M5.5 9.5V18a2.5 2.5 0 0 0 2.5 2.5h8a2.5 2.5 0 0 0 2.5-2.5V9.5']], ['path', ['d' => 'M10 20.5V15.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5']]],
        'image' => [['rect', ['x' => '3.5', 'y' => '4.5', 'width' => '17', 'height' => '15', 'rx' => '3.5']], ['circle', ['cx' => '9', 'cy' => '9.5', 'r' => '1.5']], ['path', ['d' => 'm20.5 15-4.2-4.2a1.5 1.5 0 0 0-2.1 0L6 19.5']]],
        'inbox' => [['path', ['d' => 'M3.5 13.5 6.2 6.6A2.5 2.5 0 0 1 8.5 5h7a2.5 2.5 0 0 1 2.3 1.6l2.7 6.9']], ['path', ['d' => 'M3.5 13.5V17A2.5 2.5 0 0 0 6 19.5h12a2.5 2.5 0 0 0 2.5-2.5v-3.5h-4.25l-1.25 2h-4l-1.25-2Z']]],
        'info' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '8.75']], ['path', ['d' => 'M12 11v5']], $dot(12, 7.75)],
        'key' => [['circle', ['cx' => '8', 'cy' => '16', 'r' => '5']], ['path', ['d' => 'M11.55 12.45 20.5 3.5']], ['path', ['d' => 'm15.5 8.5 3 3 2.5-2.5-3-3']]],
        'layout-dashboard' => [['rect', ['x' => '3.5', 'y' => '3.5', 'width' => '7', 'height' => '9', 'rx' => '2']], ['rect', ['x' => '13.5', 'y' => '3.5', 'width' => '7', 'height' => '5', 'rx' => '2']], ['rect', ['x' => '13.5', 'y' => '11.5', 'width' => '7', 'height' => '9', 'rx' => '2']], ['rect', ['x' => '3.5', 'y' => '15.5', 'width' => '7', 'height' => '5', 'rx' => '2']]],
        'link' => [['path', ['d' => 'M10 14a4.5 4.5 0 0 0 6.4 0l2.7-2.7a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2']], ['path', ['d' => 'M14 10a4.5 4.5 0 0 0-6.4 0l-2.7 2.7a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2']]],
        'list' => [['path', ['d' => 'M9 6.5h11']], ['path', ['d' => 'M9 12h11']], ['path', ['d' => 'M9 17.5h11']], $dot(4.5, 6.5), $dot(4.5, 12), $dot(4.5, 17.5)],
        'lock' => [['rect', ['x' => '4.5', 'y' => '10.5', 'width' => '15', 'height' => '10', 'rx' => '3.5']], ['path', ['d' => 'M8 10.5V8a4 4 0 0 1 8 0v2.5']], ['path', ['d' => 'M12 14.5v2']]],
        'log-in' => [['path', ['d' => 'M14.5 3.5h3a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3h-3']], ['path', ['d' => 'm10 7.5 4.5 4.5-4.5 4.5']], ['path', ['d' => 'M14.5 12H4']]],
        'log-out' => [['path', ['d' => 'M9.5 20.5h-3a3 3 0 0 1-3-3v-11a3 3 0 0 1 3-3h3']], ['path', ['d' => 'm15.5 16.5 4.5-4.5-4.5-4.5']], ['path', ['d' => 'M20 12H9.5']]],
        'mail' => [['rect', ['x' => '3', 'y' => '5', 'width' => '18', 'height' => '14', 'rx' => '3.5']], ['path', ['d' => 'm4 7.5 8 5.5 8-5.5']]],
        'map-pin' => [['path', ['d' => 'M12 20.75s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z']], ['circle', ['cx' => '12', 'cy' => '9.75', 'r' => '2.5']]],
        'megaphone' => [['path', ['d' => 'M3.5 10.5v3a1 1 0 0 0 .75.97l14.5 4.03a1 1 0 0 0 1.25-.97V6.47a1 1 0 0 0-1.25-.97L4.25 9.53a1 1 0 0 0-.75.97Z']], ['path', ['d' => 'M11.5 16.4a3 3 0 1 1-5.75-1.6']]],
        'menu' => [['path', ['d' => 'M4.5 6.5h15']], ['path', ['d' => 'M4.5 12h15']], ['path', ['d' => 'M4.5 17.5h15']]],
        // Communication and media
        'message-circle' => [['path', ['d' => 'M7.9 19.6A8.5 8.5 0 1 0 4.4 16.1L3.5 20.5Z']]],
        'mic' => [['rect', ['x' => '9', 'y' => '3', 'width' => '6', 'height' => '11', 'rx' => '3']], ['path', ['d' => 'M5.5 11a6.5 6.5 0 0 0 13 0']], ['path', ['d' => 'M12 17.5v3']]],
        'minus' => [['path', ['d' => 'M6 12h12']]],
        'moon' => [['path', ['d' => 'M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z']]],
        'music' => [['path', ['d' => 'M9 18V5.5l11-2v12']], ['circle', ['cx' => '6.5', 'cy' => '18', 'r' => '2.5']], ['circle', ['cx' => '17.5', 'cy' => '15.5', 'r' => '2.5']]],
        'package' => [['path', ['d' => 'M12 3.25 19.75 7.5v9L12 20.75 4.25 16.5v-9Z']], ['path', ['d' => 'M4.25 7.5 12 11.75l7.75-4.25']], ['path', ['d' => 'M12 11.75v9']], ['path', ['d' => 'm8.1 5.4 7.75 4.25']]],
        'panel-left' => [['rect', ['x' => '3.5', 'y' => '4', 'width' => '17', 'height' => '16', 'rx' => '4']], ['path', ['d' => 'M9.5 4v16']]],
        'paperclip' => [['path', ['d' => 'm20.5 11.5-8.4 8.4a5 5 0 0 1-7.1-7.1l8.4-8.4a3.33 3.33 0 0 1 4.7 4.7l-8.4 8.4a1.67 1.67 0 0 1-2.35-2.35l7.8-7.8']]],
        'pause' => [['rect', ['x' => '6.5', 'y' => '5', 'width' => '3.5', 'height' => '14', 'rx' => '1.75']], ['rect', ['x' => '14', 'y' => '5', 'width' => '3.5', 'height' => '14', 'rx' => '1.75']]],
        'pencil' => [['path', ['d' => 'M15.25 5.25a2.47 2.47 0 0 1 3.5 3.5L9 18.5l-4.5 1 1-4.5Z']], ['path', ['d' => 'm13.5 7 3.5 3.5']]],
        'percent' => [['path', ['d' => 'm18 6-12 12']], ['circle', ['cx' => '7', 'cy' => '7', 'r' => '2.25']], ['circle', ['cx' => '17', 'cy' => '17', 'r' => '2.25']]],
        'phone' => [['path', ['d' => 'M8.6 3.5H6.5A2.5 2.5 0 0 0 4 6.1C4.4 14 10 19.6 17.9 20a2.5 2.5 0 0 0 2.6-2.5v-2.1a1.5 1.5 0 0 0-1.1-1.45l-3-.85a1.5 1.5 0 0 0-1.5.4l-1.3 1.3a12 12 0 0 1-5.3-5.3l1.3-1.3a1.5 1.5 0 0 0 .4-1.5l-.85-3a1.5 1.5 0 0 0-1.45-1.1Z']]],
        'play' => [['path', ['d' => 'M7.5 5.9v12.2a1.4 1.4 0 0 0 2.1 1.2l10-6.1a1.4 1.4 0 0 0 0-2.4l-10-6.1a1.4 1.4 0 0 0-2.1 1.2Z']]],
        'plus' => [['path', ['d' => 'M12 6v12']], ['path', ['d' => 'M6 12h12']]],
        'printer' => [['path', ['d' => 'M7 9V4.5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1V9']], ['path', ['d' => 'M7 17H6a2.5 2.5 0 0 1-2.5-2.5v-3A2.5 2.5 0 0 1 6 9h12a2.5 2.5 0 0 1 2.5 2.5v3A2.5 2.5 0 0 1 18 17h-1']], ['rect', ['x' => '7', 'y' => '14', 'width' => '10', 'height' => '6.5', 'rx' => '1']]],
        'receipt' => [['path', ['d' => 'M6 3.5h12v17l-2-1.25-2 1.25-2-1.25-2 1.25-2-1.25-2 1.25Z']], ['path', ['d' => 'M9 8h6']], ['path', ['d' => 'M9 11.5h6']], ['path', ['d' => 'M9 15h3']]],
        'redo' => [['path', ['d' => 'm15 14.5 4.5-4.5L15 5.5']], ['path', ['d' => 'M19.5 10H9.75a5.25 5.25 0 0 0 0 10.5H12']]],
        'refresh-cw' => [['path', ['d' => 'M4.5 12a7.5 7.5 0 0 1 13-5.1L19 8.5']], ['path', ['d' => 'M19 4v4.5h-4.5']], ['path', ['d' => 'M19.5 12a7.5 7.5 0 0 1-13 5.1L5 15.5']], ['path', ['d' => 'M5 20v-4.5h4.5']]],
        'rotate-ccw' => [['path', ['d' => 'M4.5 12a7.5 7.5 0 1 0 2.2-5.3L4.5 9']], ['path', ['d' => 'M4.5 4.5V9H9']]],
        'rss' => [['path', ['d' => 'M4.5 11a8.5 8.5 0 0 1 8.5 8.5']], ['path', ['d' => 'M4.5 4.5a15 15 0 0 1 15 15']], ['circle', ['cx' => '5.5', 'cy' => '18.5', 'r' => '1']]],
        // Editing and files
        'save' => [['path', ['d' => 'M6 3.5h9.5l5 5V18a2.5 2.5 0 0 1-2.5 2.5H6A2.5 2.5 0 0 1 3.5 18V6A2.5 2.5 0 0 1 6 3.5Z']], ['path', ['d' => 'M7.5 20.5v-6a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v6']], ['path', ['d' => 'M7.5 3.5v4h7']]],
        'scissors' => [['circle', ['cx' => '6', 'cy' => '6', 'r' => '3']], ['circle', ['cx' => '6', 'cy' => '18', 'r' => '3']], ['path', ['d' => 'M20 4 8.12 15.88']], ['path', ['d' => 'M14.47 14.48 20 20']], ['path', ['d' => 'M8.12 8.12 12 12']]],
        'search' => [['circle', ['cx' => '10.5', 'cy' => '10.5', 'r' => '6.75']], ['path', ['d' => 'm15.5 15.5 4.5 4.5']]],
        'send' => [['path', ['d' => 'M20.5 3.5 3.5 10.25l7 3.25 3.25 7Z']], ['path', ['d' => 'm20.5 3.5-10 10']]],
        'settings' => [['path', ['d' => 'M9.91 5.74 10.59 3.11 13.41 3.11 14.09 5.74 14.94 6.09 17.29 4.72 19.28 6.71 17.91 9.06 18.26 9.91 20.89 10.59 20.89 13.41 18.26 14.09 17.91 14.94 19.28 17.29 17.29 19.28 14.94 17.91 14.09 18.26 13.41 20.89 10.59 20.89 9.91 18.26 9.06 17.91 6.71 19.28 4.72 17.29 6.09 14.94 5.74 14.09 3.11 13.41 3.11 10.59 5.74 9.91 6.09 9.06 4.72 6.71 6.71 4.72 9.06 6.09Z']], ['circle', ['cx' => '12', 'cy' => '12', 'r' => '2.75']]],
        'share' => [['circle', ['cx' => '18', 'cy' => '5.5', 'r' => '2.5']], ['circle', ['cx' => '6', 'cy' => '12', 'r' => '2.5']], ['circle', ['cx' => '18', 'cy' => '18.5', 'r' => '2.5']], ['path', ['d' => 'm8.2 10.8 7.6-4.1']], ['path', ['d' => 'm8.2 13.2 7.6 4.1']]],
        'shield-check' => [['path', ['d' => 'M12 3.5 5 6.25v5.5c0 4.3 2.9 7.4 7 8.75 4.1-1.35 7-4.45 7-8.75v-5.5Z']], ['path', ['d' => 'm9 12 2.2 2.2 4-4.2']]],
        'shopping-bag' => [['path', ['d' => 'M6 7.5h12l1 10.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 18Z']], ['path', ['d' => 'M9 10V7a3 3 0 0 1 6 0v3']]],
        // Commerce and money
        'shopping-cart' => [['path', ['d' => 'M3 3.5h2l2.4 11.1a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 1.95-1.55L20.5 7.5H6']], ['circle', ['cx' => '9.5', 'cy' => '20', 'r' => '1.25']], ['circle', ['cx' => '17', 'cy' => '20', 'r' => '1.25']]],
        'star' => [['path', ['d' => 'M12 3.6 14.41 9.28 20.56 9.82 15.9 13.87 17.29 19.88 12 16.7 6.71 19.88 8.1 13.87 3.44 9.82 9.59 9.28Z']]],
        'store' => [['path', ['d' => 'M3.5 9 5 4.5h14L20.5 9v.5a2.83 2.83 0 0 1-5.67 0 2.83 2.83 0 0 1-5.67 0 2.83 2.83 0 0 1-5.66 0Z']], ['path', ['d' => 'M5 12v6a2.5 2.5 0 0 0 2.5 2.5h9A2.5 2.5 0 0 0 19 18v-6']], ['path', ['d' => 'M10 20.5V16a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4.5']]],
        'sun' => [['circle', ['cx' => '12', 'cy' => '12', 'r' => '3.75']], ['path', ['d' => 'M12 3.25v1.5']], ['path', ['d' => 'M12 19.25v1.5']], ['path', ['d' => 'M3.25 12h1.5']], ['path', ['d' => 'M19.25 12h1.5']], ['path', ['d' => 'm5.8 5.8 1.05 1.05']], ['path', ['d' => 'm17.15 17.15 1.05 1.05']], ['path', ['d' => 'm5.8 18.2 1.05-1.05']], ['path', ['d' => 'm17.15 6.85 1.05-1.05']]],
        'tag' => [['path', ['d' => 'M3.5 6v5.2a2 2 0 0 0 .6 1.4l7.8 7.8a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8L12.1 4.6a2 2 0 0 0-1.4-.6H5.5a2 2 0 0 0-2 2Z']], ['circle', ['cx' => '8', 'cy' => '8.5', 'r' => '1.25']]],
        'thumbs-up' => [['path', ['d' => 'M7.5 10.5v10']], ['path', ['d' => 'M7.5 10.5l3.6-6.6a1.75 1.75 0 0 1 3.3 1L13.6 9.5h4.6a2 2 0 0 1 1.95 2.45l-1.5 6.5a2 2 0 0 1-1.95 1.55H7.5']], ['path', ['d' => 'M7.5 10.5H5a1.5 1.5 0 0 0-1.5 1.5v7a1.5 1.5 0 0 0 1.5 1.5h2.5']]],
        'trash' => [['path', ['d' => 'M4.5 6.5h15']], ['path', ['d' => 'M18 6.5V18a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 6 18V6.5']], ['path', ['d' => 'M9 6.5V5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 5v1.5']], ['path', ['d' => 'M10 10.5v6']], ['path', ['d' => 'M14 10.5v6']]],
        'triangle-alert' => [['path', ['d' => 'M10.27 4.5 3.3 16.75a2.25 2.25 0 0 0 1.95 3.35h13.5a2.25 2.25 0 0 0 1.95-3.35L13.73 4.5a2 2 0 0 0-3.46 0Z']], ['path', ['d' => 'M12 9.5v4']], $dot(12, 16.75)],
        'truck' => [['path', ['d' => 'M14.5 16.5V6.5a1 1 0 0 0-1-1h-9a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h1']], ['path', ['d' => 'M14.5 9h3.1a1 1 0 0 1 .8.4l2 2.6a1 1 0 0 1 .2.6v2.9a1 1 0 0 1-1 1H19']], ['circle', ['cx' => '7.5', 'cy' => '17', 'r' => '2']], ['circle', ['cx' => '17', 'cy' => '17', 'r' => '2']], ['path', ['d' => 'M9.5 17H15']]],
        'undo' => [['path', ['d' => 'M9 14.5 4.5 10 9 5.5']], ['path', ['d' => 'M4.5 10h9.75a5.25 5.25 0 0 1 0 10.5H12']]],
        'upload' => [['path', ['d' => 'M12 15.5V4.5']], ['path', ['d' => 'm7.5 9 4.5-4.5L16.5 9']], ['path', ['d' => 'M4.5 15v2.5a2.5 2.5 0 0 0 2.5 2.5h10a2.5 2.5 0 0 0 2.5-2.5V15']]],
        'user' => [['circle', ['cx' => '12', 'cy' => '8.25', 'r' => '3.75']], ['path', ['d' => 'M4.75 20a7.25 7.25 0 0 1 14.5 0']]],
        'user-plus' => [['circle', ['cx' => '9.5', 'cy' => '8.25', 'r' => '3.75']], ['path', ['d' => 'M2.75 20a6.75 6.75 0 0 1 13.5 0']], ['path', ['d' => 'M19 8v6']], ['path', ['d' => 'M16 11h6']]],
        'users' => [['circle', ['cx' => '9.5', 'cy' => '8.5', 'r' => '3.5']], ['path', ['d' => 'M3 20a6.5 6.5 0 0 1 13 0']], ['path', ['d' => 'M15.5 5.1a3.5 3.5 0 0 1 0 6.8']], ['path', ['d' => 'M18 14.1a6.5 6.5 0 0 1 3 5.9']]],
        'video' => [['rect', ['x' => '3', 'y' => '6', 'width' => '12.5', 'height' => '12', 'rx' => '3']], ['path', ['d' => 'm15.5 10.5 4.7-2.6a.75.75 0 0 1 1.1.65v6.9a.75.75 0 0 1-1.1.65l-4.7-2.6']]],
        'volume-2' => [['path', ['d' => 'M4 9.5v5a1 1 0 0 0 1 1h2.5l4.3 3.6a.75.75 0 0 0 1.2-.6V5.5a.75.75 0 0 0-1.2-.6L7.5 8.5H5a1 1 0 0 0-1 1Z']], ['path', ['d' => 'M16 9a4 4 0 0 1 0 6']], ['path', ['d' => 'M18.75 6.5a7.5 7.5 0 0 1 0 11']]],
        'wallet' => [['path', ['d' => 'M17 6v-.5A2.5 2.5 0 0 0 14.5 3H6a2.5 2.5 0 0 0-2.5 2.5']], ['rect', ['x' => '3.5', 'y' => '6', 'width' => '17', 'height' => '13.5', 'rx' => '3.5']], ['path', ['d' => 'M20.5 11h-3.25a1.75 1.75 0 0 0 0 3.5h3.25']]],
        'x' => [['path', ['d' => 'm6.5 6.5 11 11']], ['path', ['d' => 'm17.5 6.5-11 11']]],
    ];

    // Decorative by default (hidden from screen readers). Given aria-label or aria-labelledby, it is a labelled image.
    $named = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $svg = $attributes->class(['shrink-0'])->merge($named ? ['role' => 'img'] : ['aria-hidden' => 'true']);

    // Not one of ours: Blade Icons draws it when the app has it installed, so the icon props of every widget take any
    // icon from its sets (lucide-rocket, heroicon-o-bolt). Looked up by name, so nothing here needs that package.
    $drawn = null;
    if (!isset($icons[$name]) && app()->bound('BladeUI\Icons\Factory')) {
        try {
            $drawn = app('BladeUI\Icons\Factory')->svg($name, (string) $svg->get('class'), $svg->except('class')->getAttributes())->toHtml();
        } catch (\Throwable $missing) {
            // Falls through to the message below, which lists our names; Blade Icons' own reason stays attached.
        }
    }

    // A typo should fail loudly in development, not render an empty gap.
    $shapes = $icons[$name] ?? ($drawn !== null ? [] : throw new \InvalidArgumentException(
        "Unknown icon [{$name}]. Available: ".implode(', ', array_keys($icons)).'. Add it to resources/views/widget/icon/index.blade.php, or install Blade Icons (blade-ui-kit/blade-icons) and use a name from one of its sets, e.g. lucide-rocket.',
        previous: $missing ?? null,
    ));
@endphp

@if ($drawn !== null)
{{-- Raw: the SVG comes from an icon set the app installed, picked by the name the code passes, never from visitor input. --}}
{!! $drawn !!}@else
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $svg }}>
    @foreach ($shapes as [$tag, $shape])
        <{{ $tag }} {{ new \Illuminate\View\ComponentAttributeBag($shape) }} />
    @endforeach
</svg>@endif<?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
