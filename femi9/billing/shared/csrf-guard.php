<?php
/**
 * Cross-site request guard, called from every portal's checksession.php.
 *
 * Stops another website from making a logged-in user's browser change data
 * (CSRF) without touching any link or form:
 *
 *  - A request is "cross-site" when the browser says so (Sec-Fetch-Site:
 *    cross-site), or - for browsers/clients that don't send Fetch Metadata -
 *    when its Origin / Referer host differs from this host.
 *  - Cross-site POSTs are refused.
 *  - Cross-site GETs are refused unless they are plain top-level page
 *    navigations (a link click) to a page that does not change data. Anything
 *    that is not a navigation (images, scripts, fetch, iframes) is refused,
 *    and so is a navigation to a script whose name marks it as an action
 *    (delete / active / approve / toggle / undo / ... ).
 *  - A request carrying none of these signals (curl, old clients, a typed URL
 *    or bookmark, privacy tools stripping headers) is allowed: it cannot be
 *    forged by a web page, which always sends at least one of them.
 *
 * Also hardens the session cookie (HttpOnly, SameSite=Lax, Secure on HTTPS)
 * when the session has not started yet.
 */
if (!function_exists('femi_csrf_guard')) {
    function femi_csrf_guard(): void
    {
        if (PHP_SAPI === 'cli') { return; }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_samesite', 'Lax');
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            if ($https) { @ini_set('session.cookie_secure', '1'); }
        }

        // Payment-gateway return pages (success_*.php) are entered by the gateway
        // redirecting the customer's browser back, which is cross-site by design.
        $self_script = strtolower(basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)));
        if (strpos($self_script, 'success_') === 0) { return; }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) { return; }

        $norm = static function (string $h): string {
            $h = strtolower(trim($h));
            $h = preg_replace('/:\d+$/', '', $h);
            return preg_replace('/^www\./', '', $h);
        };
        $self = $norm((string)($_SERVER['HTTP_HOST'] ?? ''));

        $site = strtolower($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        $cross = false;
        if ($site !== '') {
            $cross = ($site === 'cross-site');
        } else {
            foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $k) {
                $v = $_SERVER[$k] ?? '';
                if ($v === '' || $v === 'null') { continue; }
                $host = parse_url($v, PHP_URL_HOST);
                if ($host) { $cross = ($norm($host) !== $self); break; }
            }
        }
        if (!$cross) { return; }

        $block = true;
        if ($method === 'GET') {
            $mode = strtolower($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
            $dest = strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
            // Without Fetch Metadata we cannot tell a link click from an <img>;
            // a cross-site referrer on a GET is treated as a navigation.
            $isNav = ($mode === '' || $mode === 'navigate') && ($dest === '' || $dest === 'document');
            $script = strtolower(pathinfo((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), PATHINFO_FILENAME));
            $mutating = (bool)preg_match(
                '/(delete|(^|[-_])del([-_]|$)|remove|cancel|(^|[-_])(in)?active|approv|reject|toggle|undo|reset|action|submit|update|confirm|convert|transfer|assign|unskip|dispatch|upload|finish|backfill)/',
                $script
            );
            $block = !$isNav || $mutating;
        }

        if ($block) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Blocked: cross-site request.');
        }
    }
}
