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

        $_SESSION['admin_user_id']  = (int)$user['id'];
        $_SESSION['admin_username'] = (string)$user['username'];
        $_SESSION['admin_role']     = (string)$user['role'];
        $_SESSION['login_time']     = time();

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
    }

    /**
     * Require a login that's no older than $maxSeconds. Useful for sensitive
     * pages — bumps the user back to the login page if the session is stale.
     */
    public function requireFreshLogin(int $maxSeconds = 3600): void
    {
        $this->requireAuth();

        $loginTime = (int)($_SESSION['login_time'] ?? 0);
        if ($loginTime === 0 || (time() - $loginTime) > $maxSeconds) {
            $this->logout();
            $_SESSION['flash'] = [
                'type'    => 'info',
                'message' => 'Your session has expired. Please sign in again.',
            ];
            header('Location: login.php');
            exit;
        }
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
