<?php
namespace PrintHub;

/**
 * Auth  -  login, logout, and "who is the current user?".
 * All methods are static, so we call them like Auth::user() without "new".
 */
class Auth
{
    /** Check email + password. Returns the logged-in Account or throws ValidationException. */
    public static function login(string $email, string $password): Account
    {
        $row = (new User())->findByEmail($email);

        // password_verify() compares the typed password with the stored hash.
        // We show the SAME message for "no such email" and "wrong password" on purpose,
        // so attackers cannot learn which emails exist.
        if ($row === null || !password_verify($password, $row['password'])) {
            throw new ValidationException(['Invalid email or password.']);
        }

        session_regenerate_id(true); // new session id after login = blocks "session fixation"

        $account = Account::fromArray([
            'id' => $row['user_id'], 'name' => $row['name'], 'email' => $row['email'], 'role' => $row['role'],
        ]);
        $_SESSION['user'] = $account->toArray();
        return $account;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** The logged-in Account (Customer or Admin), or null for visitors. */
    public static function user(): ?Account
    {
        if (empty($_SESSION['user'])) {
            return null;
        }
        return Account::fromArray($_SESSION['user']);
    }

    /** Page guard: visitors are sent to the login page. */
    public static function requireLogin(): Account
    {
        $user = self::user();
        if ($user === null) {
            // remember where they wanted to go, so we can send them back after login
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, BASE_URL . '/') === 0) {
                $_SESSION['after_login'] = substr($uri, strlen(BASE_URL) + 1);
            }
            flash('error', 'Please log in first.');
            redirect('login.php');
        }
        return $user;
    }

    /** Page guard: only the admin may enter. */
    public static function requireAdmin(): Account
    {
        $user = self::requireLogin();
        if (!$user->isAdmin()) {
            flash('error', 'That page is for the admin only.');
            redirect($user->getHomePage());
        }
        return $user;
    }
}
