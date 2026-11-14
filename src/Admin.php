<?php
namespace PrintHub;

/** The shop owner. Same methods as Customer, but with DIFFERENT behaviour (polymorphism). */
class Admin extends Account
{
    public function getHomePage(): string
    {
        return 'admin/index.php';
    }

    public function getRoleLabel(): string
    {
        return 'Admin';
    }

    public function isAdmin(): bool  // OVERRIDES the version in Account
    {
        return true;
    }
}
