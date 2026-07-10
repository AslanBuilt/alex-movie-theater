<?php
declare(strict_types=1);

/**
 * PosAuth — PIN-based authentication for the employee POS at /pos.
 *
 * Runs on its OWN session (ALEX_POS_SESS), independent of the admin area
 * (ALEX_ADMIN_SESS). Access is granted to either:
 *   - an employee who enters a valid PIN, OR
 *   - an already-logged-in admin (read from the admin session at bootstrap).
 *
 * Security: bcrypt-hashed PINs (cost 12, matching admin_users), 5 failed
 * attempts → 15-minute lockout (tracked in employees.failed_attempts /
 * locked_until), generic "Incorrect PIN" message, session id regeneration on
 * login, and a POS-scoped CSRF token validated on every mutation.
 */
final class PosAuth
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Bootstrap both sessions safely. Reads whether an admin is logged in from
     * the admin session, then closes it and opens the POS session for the
     * remainder of the request. Two cookies coexist in the browser.
     *
     * Must be called before any output. Returns whether an admin is present.
     */
    public static function bootstrap(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // A session is already open from elsewhere; close it first.
            session_write_close();
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // 1. Peek at the admin session to detect an active admin login — but only
        //    if an admin cookie is actually present, so non-admin employees never
        //    spawn an empty ALEX_ADMIN_SESS server-side session.
        $isAdmin   = false;
        $adminName = '';
        if (isset($_COOKIE['ALEX_ADMIN_SESS'])) {
            session_set_cookie_params($cookieParams);
            session_name('ALEX_ADMIN_SESS');
            session_start();
            $isAdmin   = !empty($_SESSION['admin_user_id']);
            $adminName = (string)($_SESSION['admin_username'] ?? '');
            session_write_close();
        }

        // 2. Open the real POS session for the rest of the request.
        session_set_cookie_params($cookieParams);
        session_name('ALEX_POS_SESS');
        session_start();

        if ($isAdmin) {
            $_SESSION['pos_admin_present'] = true;
            $_SESSION['pos_admin_name']    = $adminName;
        } else {
            unset($_SESSION['pos_admin_present'], $_SESSION['pos_admin_name']);
        }

        return $isAdmin;
    }

    /** True if an employee PIN session OR an admin session is active. */
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['pos_employee_id']) || !empty($_SESSION['pos_admin_present']);
    }

    /** Display name for the current operator. */
    public function operatorName(): string
    {
        if (!empty($_SESSION['pos_employee_name'])) {
            return (string)$_SESSION['pos_employee_name'];
        }
        if (!empty($_SESSION['pos_admin_present'])) {
            $n = (string)($_SESSION['pos_admin_name'] ?? '');
            return $n !== '' ? $n : 'Admin';
        }
        return 'Operator';
    }

    /**
     * Attempt a PIN login. Returns one of:
     *   ['ok' => true]
     *   ['ok' => false, 'error' => '...', 'locked' => bool]
     *
     * Uses a generic "Incorrect PIN" message for both unknown and wrong PINs.
     */
    public function login(string $pin): array
    {
        $pin = trim($pin);
        if ($pin === '' || !ctype_digit($pin)) {
            return ['ok' => false, 'error' => 'Incorrect PIN.', 'locked' => false];
        }

        try {
            // Pull all active employees; PIN is bcrypt-hashed so we cannot look
            // up by PIN directly. The default deployment has a single employee,
            // so this is cheap.
            $stmt = $this->db->query(
                'SELECT id, name, pin_hash, failed_attempts, locked_until
                 FROM employees WHERE is_active = 1'
            );
            $employees = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[PosAuth::login] lookup failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Sign-in is temporarily unavailable.', 'locked' => false];
        }

        $now = new DateTimeImmutable('now');

        foreach ($employees as $emp) {
            if (!password_verify($pin, (string)$emp['pin_hash'])) {
                continue;
            }

            // PIN matched — but the account may be locked.
            $lockedUntil = $emp['locked_until'] !== null
                ? new DateTimeImmutable((string)$emp['locked_until'])
                : null;
            if ($lockedUntil !== null && $lockedUntil > $now) {
                return [
                    'ok'     => false,
                    'error'  => 'Too many attempts. Try again in a few minutes.',
                    'locked' => true,
                ];
            }

            // Success. Reset the failure counter and stamp the login.
            $this->resetAttempts((int)$emp['id']);
            session_regenerate_id(true);
            $_SESSION['pos_employee_id']   = (int)$emp['id'];
            $_SESSION['pos_employee_name'] = (string)$emp['name'];
            $_SESSION['pos_login_time']    = time();
            return ['ok' => true];
        }

        // No PIN matched any active employee. Register a failed attempt against
        // every active account (we cannot tell which one was intended), which
        // also locks the seeded single-employee deployment after 5 tries.
        $this->registerFailure();
        return ['ok' => false, 'error' => 'Incorrect PIN.', 'locked' => false];
    }

    /**
     * Verify an employee PIN without creating a POS session. Used by kiosk cash
     * checkout so the physical register remains protected while the kiosk stays
     * unauthenticated.
     */
    public function verifyPinOnly(string $pin): array
    {
        $pin = trim($pin);
        if ($pin === '' || !ctype_digit($pin)) {
            return ['ok' => false, 'error' => 'Incorrect PIN.', 'locked' => false];
        }

        try {
            $stmt = $this->db->query(
                'SELECT id, pin_hash, failed_attempts, locked_until
                 FROM employees WHERE is_active = 1'
            );
            $employees = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('[PosAuth::verifyPinOnly] lookup failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Sign-in is temporarily unavailable.', 'locked' => false];
        }

        $now = new DateTimeImmutable('now');

        foreach ($employees as $emp) {
            if (!password_verify($pin, (string)$emp['pin_hash'])) {
                continue;
            }

            $lockedUntil = $emp['locked_until'] !== null
                ? new DateTimeImmutable((string)$emp['locked_until'])
                : null;
            if ($lockedUntil !== null && $lockedUntil > $now) {
                return ['ok' => false, 'error' => 'Too many attempts. Try again in a few minutes.', 'locked' => true];
            }

            $this->resetAttempts((int)$emp['id']);
            return ['ok' => true, 'error' => '', 'locked' => false];
        }

        $this->registerFailure();
        return ['ok' => false, 'error' => 'Incorrect PIN.', 'locked' => false];
    }

    private function resetAttempts(int $employeeId): void
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE employees
                 SET failed_attempts = 0, locked_until = NULL, last_login = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $employeeId]);
        } catch (\Throwable $e) {
            error_log('[PosAuth::resetAttempts] ' . $e->getMessage());
        }
    }

    /**
     * Increment failed_attempts on all active employees; lock any that reach
     * the threshold for LOCKOUT_MINUTES.
     */
    private function registerFailure(): void
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE employees
                 SET failed_attempts = failed_attempts + 1,
                     locked_until = CASE
                         WHEN failed_attempts + 1 >= :max
                         THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE)
                         ELSE locked_until
                     END
                 WHERE is_active = 1'
            );
            $stmt->bindValue(':max', self::MAX_ATTEMPTS, PDO::PARAM_INT);
            $stmt->bindValue(':mins', self::LOCKOUT_MINUTES, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('[PosAuth::registerFailure] ' . $e->getMessage());
        }
    }

    /** Clear only the employee identity (full logout). */
    public function logout(): void
    {
        unset(
            $_SESSION['pos_employee_id'],
            $_SESSION['pos_employee_name'],
            $_SESSION['pos_login_time']
        );
    }

    public function generateCsrfToken(): string
    {
        if (empty($_SESSION['pos_csrf_token'])) {
            $_SESSION['pos_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['pos_csrf_token'];
    }

    public function validateCsrf(string $token): bool
    {
        $expected = (string)($_SESSION['pos_csrf_token'] ?? '');
        if ($expected === '' || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }
}
