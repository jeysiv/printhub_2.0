<?php
namespace PrintHub;

/** A normal shop customer. INHERITS everything from Account and fills in the abstract parts. */
class Customer extends Account
{
    public function getHomePage(): string
    {
        return 'my_orders.php';
    }

    public function getRoleLabel(): string
    {
        return 'Customer';
    }
}
