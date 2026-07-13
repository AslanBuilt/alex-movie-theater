<?php
declare(strict_types=1);

/**
 * AdminAuth — session-based authentication for the Alex Theatre admin panel.
 *
 * Owns: session lifecycle, password verification against admin_users,
 * CSRF token generation/validation, and helper guards for protected pages.
 */
class AdminAuth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Harden session cookie before starting.
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? null) == 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_name('ALEX_ADMIN_SESS');
            session_start();
        }
    }

    /**
     * Attempt to log a user in. Returns true on success, false otherwise.
     */
    public function login(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id, username, password_hash, role, is_active
                 FROM admin_users
                 WHERE username = :u AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('AdminAuth::login lookup failed: ' . $e->getMessage());
            return false;
        }

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        // Regenerate session ID to prevent fixation.
        session_regenerate_id(true);

        // session_regenerate_id(true) preserves session data, so any flash
        // set before this login (e.g. requireAuth()'s "Please sign in..." on
        // the redirect that brought the user here, or logout.php's message)
        // would otherwise ride through and render on the first post-login
        // page — login.php itself never reads/clears session flash.
        unset($_SESSION['flash']);

        $_SESSION['admin_user_id']    = (int)$user['id'];
        $_SESSION['admin_username']   = (string)$user['username'];
        $_SESSION['admin_role']       = (string)$user['role'];
        $_SESSION['login_time']       = time();
        $_SESSION['admin_last_active'] = time();

        try {
            $upd = $this->db->prepare(
                'UPDATE admin_users SET last_login = NOW() WHERE id = :id'
            );
            $upd->execute([':id' => (int)$user['id']]);
        } catch (PDOException $e) {
            error_log('AdminAuth::login last_login update failed: ' . $e->getMessage());
        }

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Every admin page calls this. Bounces to login if not authenticated, or
     * if the session has been idle longer than ADMIN_SESSION_TTL — an admin
     * who walks away from an open admin panel stays logged in forever
     * otherwise. (requireFreshLogin(), a login-age check, used to live here
     * but was dead code — called from nowhere — and this idle-based check
     * supersedes what it was for, so it was removed instead of wired in.)
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['flash'] = [
                'type'    => 'info',
                'message' => 'Please sign in to access the admin panel.',
            ];
            header('Location: login.php');
            exit;
        }

        $lastActive = (int)($_SESSION['admin_last_active'] ?? 0);
        if ($lastActive > 0 && (time() - $lastActive) > ADMIN_SESSION_TTL) {
            $this->logout();
            $_SESSION['flash'] = [
                'type'    => 'info',
                'message' => 'Your session expired. Please log in again.',
            ];
            header('Location: login.php?reason=expired');
            exit;
        }

        $_SESSION['admin_last_active'] = time();
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_user_id']);
    }

    /**
     * @return array{id:int,username:string,role:string,login_time:int}|null
     */
    public function getUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id'         => (int)$_SESSION['admin_user_id'],
            'username'   => (string)($_SESSION['admin_username'] ?? ''),
            'role'       => (string)($_SESSION['admin_role'] ?? ''),
            'login_time' => (int)($_SESSION['login_time'] ?? 0),
        ];
    }

    private const LOGIN_ATTEMPT_WINDOW_MINUTES = 10;
    private const LOGIN_ATTEMPT_MAX            = 5;

    /**
     * DB-backed login-attempt lockout, keyed by IP. Session-based counters
     * (the previous implementation) reset the instant a private/incognito
     * window is opened, making the lockout cosmetic — moving the counter
     * server-side closes that bypass. No trusted reverse proxy sits in front
     * of this host (confirmed: no X-Forwarded-For handling exists anywhere
     * else in this codebase, e.g. RateLimiter::clientIp() also uses
     * REMOTE_ADDR directly), so REMOTE_ADDR is the real client IP here.
     */
    public function isIpLockedOut(string $ip): bool
    {
        try {
            // LOGIN_ATTEMPT_WINDOW_MINUTES is a private int constant (not user
            // input) inlined directly — binding a placeholder inside INTERVAL
            // is flaky under native prepares (Database uses EMULATE_PREPARES
            // => false), so this avoids that class of problem entirely.
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM admin_login_attempts
                 WHERE ip_address = :ip
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . self::LOGIN_ATTEMPT_WINDOW_MINUTES . ' MINUTE)'
            );
            $stmt->execute([':ip' => $ip]);
            return (int)$stmt->fetchColumn() >= self::LOGIN_ATTEMPT_MAX;
        } catch (PDOException $e) {
            error_log('AdminAuth::isIpLockedOut failed: ' . $e->getMessage());
            return false; // fail open — a DB hiccup must not lock every admin out
        }
    }

    public function recordFailedAttempt(string $ip): void
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO admin_login_attempts (ip_address) VALUES (:ip)');
            $stmt->execute([':ip' => $ip]);
        } catch (PDOException $e) {
            error_log('AdminAuth::recordFailedAttempt failed: ' . $e->getMessage());
        }
    }

    /** Clear this IP's attempt history on a successful login. */
    public function clearFailedAttempts(string $ip): void
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM admin_login_attempts WHERE ip_address = :ip');
            $stmt->execute([':ip' => $ip]);
        } catch (PDOException $e) {
            error_log('AdminAuth::clearFailedAttempts failed: ' . $e->getMessage());
        }
    }

    public function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    public function validateCsrf(string $token): bool
    {
        $expected = (string)($_SESSION['csrf_token'] ?? '');
        if ($expected === '' || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }
}
