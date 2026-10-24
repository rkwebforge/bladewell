// remember="sidebar": the script writes the cookie, so Laravel can't have encrypted it. Leave it out of encryption in
// bootstrap/app.php, or it reads as empty and every page starts expanded:
->withMiddleware(function (Middleware $middleware): void {
    $middleware->encryptCookies(except: ['sidebar']);
})

// Then pass it back in your layout. It holds "collapsed" or "expanded"; with no cookie yet, the sidebar starts expanded:
// <x-widget.sidebar-layout remember="sidebar" :collapsed="request()->cookie('sidebar') === 'collapsed'">
