<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/** Select a separate Laravel session cookie for each browser-tab token. */
class SelectTabSession
{
    public function __construct(private readonly SessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Ezi-Tab-Session') ?? $request->query('__tab');
        $baseCookie = (string) config('session.cookie');

        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{32}\z/i', $token)) {
            if ($request->isMethodSafe()
                && $request->header('Sec-Fetch-Dest') === 'document'
                && str_contains((string) $request->header('Accept'), 'text/html')) {
                return redirect()->to($request->fullUrlWithQuery(['__tab' => bin2hex(random_bytes(16))]));
            }

            return $next($request);
        }

        $previousCookie = $baseCookie;
        config(['session.cookie' => $baseCookie.'-tab-'.substr(hash('sha256', strtolower($token)), 0, 24)]);
        $this->sessions->forgetDrivers();

        try {
            /** @var Response $response */
            $response = $next($request);

            if ($response->isRedirect() && $response->headers->has('Location')) {
                $location = $response->headers->get('Location');
                $target = parse_url($location);
                $targetScheme = $target['scheme'] ?? $request->getScheme();
                $targetHost = $target['host'] ?? $request->getHost();
                $targetPort = $target['port'] ?? ($targetScheme === 'https' ? 443 : 80);
                $requestPort = $request->getPort();
                $sameOrigin = strtolower($targetScheme) === strtolower($request->getScheme())
                    && strtolower($targetHost) === strtolower($request->getHost())
                    && $targetPort === $requestPort;

                if (is_string($location) && $sameOrigin) {
                    $query = [];
                    parse_str($target['query'] ?? '', $query);
                    $query['__tab'] = strtolower($token);
                    $path = ($target['scheme'] ?? null)
                        ? ($target['scheme'].'://'.$targetHost.((isset($target['port']) ? ':'.$target['port'] : '')).($target['path'] ?? '/'))
                        : ($target['path'] ?? '/');
                    $response->headers->set('Location', $path.'?'.http_build_query($query).(isset($target['fragment']) ? '#'.$target['fragment'] : ''));
                }
            }

            return $response;
        } finally {
            config(['session.cookie' => $previousCookie]);
            $this->sessions->forgetDrivers();
        }
    }
}
