<?php

namespace Acelle\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Acelle\Model\User;
use Acelle\Model\Setting;
use Acelle\Cashier\Cashier;
use Acelle\Model\Invoice;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function delete(User $user, Invoice $invoice, $role)
    {
        switch ($role) {
            case 'admin':
                $can = $invoice->isNew() || $invoice->isClaimed();
                break;
            case 'customer':
                $can = ($invoice->isNew() || $invoice->isClaimed()) && $invoice->customer_id == $user->customer->id;
                break;
        }

        return $can;
    }
}
