<?php
namespace PrintHub;

/**
 * Account  -  "who is logged in right now". Customer and Admin are its two kinds.
 *
 * OOP CONCEPTS
 *   ABSTRACTION  : you cannot create a plain Account, only a Customer or an Admin.
 *   ENCAPSULATION: properties are PRIVATE/PROTECTED; other code reads them with getters.
 *   POLYMORPHISM : getHomePage() and getRoleLabel() are declared here but each child
 *                  answers DIFFERENTLY. The pages just call $user->getHomePage() and the
 *                  right answer appears, without any "if admin ... else ..." in the page.
 */
abstract class Account
{
    private int $id;
    private string $name;
    private string $email;

    public function __construct(int $id, string $name, string $email)
    {
        $this->id    = $id;
        $this->name  = $name;
        $this->email = $email;
    }

    public function getId(): int      { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }

    // Every child MUST write these two methods:
    abstract public function getHomePage(): string;   // where to go after login
    abstract public function getRoleLabel(): string;  // text shown in the menu

    public function isAdmin(): bool
    {
        return false; // Admin overrides this to return true
    }

    /** Turn the object into a small array so it can be stored in $_SESSION. */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->isAdmin() ? 'admin' : 'customer'];
    }

    /** FACTORY: read a role and build the correct kind of object. */
    public static function fromArray(array $data): Account
    {
        if ($data['role'] === 'admin') {
            return new Admin((int)$data['id'], $data['name'], $data['email']);
        }
        return new Customer((int)$data['id'], $data['name'], $data['email']);
    }
}
