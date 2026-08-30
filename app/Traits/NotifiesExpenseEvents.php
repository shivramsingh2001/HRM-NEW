<?php
// app/Traits/NotifiesExpenseEvents.php

namespace App\Traits;

use App\Models\User;
use App\Services\ExpenseNotificationService;

trait NotifiesExpenseEvents
{
    /**
     * Notify about expense submission
     */
    protected function notifyExpenseSubmitted($expense)
    {
        return app(ExpenseNotificationService::class)
            ->notifyExpenseSubmitted($expense);
    }

    /**
     * Notify reporting head only
     */
    protected function notifyReportingHead($expense)
    {
        $service = app(ExpenseNotificationService::class);
        $reportingHead = $service->getReportingHead($expense->user_id);
        
        if ($reportingHead) {
            $reportingHead->notify(new \App\Notifications\ExpenseSubmittedNotification($expense));
        }
    }

    /**
     * Notify all HR users
     */
    protected function notifyAllHr($expense)
    {
        $hrUsers = User::where('role', 'hr')->where('status', 1)->get();
        
        foreach ($hrUsers as $hr) {
            $hr->notify(new \App\Notifications\ExpenseSubmittedNotification($expense));
        }
    }

    /**
     * Notify all Admin users
     */
    protected function notifyAllAdmins($expense)
    {
        $admins = User::where('role', 'admin')->where('status', 1)->get();
        
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\ExpenseSubmittedNotification($expense));
        }
    }
}