<?php
namespace PrintHub;

/**
 * User  -  talks to the `users` TABLE (find a user, register a user).
 * (Account = the person logged in.  User = the database access for that table.)
 */
class User extends BaseModel
{
    protected function table(): string      { return 'users'; }
    protected function primaryKey(): string { return 'user_id'; }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Create a new CUSTOMER account. Returns the new user_id. */
    public function register(string $name, string $email, string $password): int
    {
        if ($this->findByEmail($email) !== null) {
            throw new ValidationException(['That email is already registered. Please log in instead.']);
        }

        // password_hash() scrambles the password so even we cannot read it back.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, 'customer')"
        );
        $stmt->execute(['name' => $name, 'email' => $email, 'password' => $hash]);
        return (int)$this->db->lastInsertId();
    }
}
