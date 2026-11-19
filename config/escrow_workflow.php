<?php
declare(strict_types=1);

/** @return list<string> */
function escrow_workflow_actions(string $status, string $role): array
{
    return array_keys(escrow_workflow_transitions($status, $role));
}

function escrow_workflow_next(string $status, string $role, string $action): string
{
    $next = escrow_workflow_transitions($status, $role)[$action] ?? null;
    if ($next === null) {
        throw new DomainException('Escrow action is not permitted for this state and role.');
    }
    return $next;
}

/** @return array<string, string> */
function escrow_workflow_transitions(string $status, string $role): array
{
    static $edges = [
        'offered' => [
            'accept' => ['awaiting_funding', ['recipient']],
            'reject' => ['rejected', ['recipient']],
            'counter' => ['offered', ['recipient']],
            'withdraw' => ['cancelled', ['proposer']],
            'expire' => ['expired', ['worker']],
        ],
        'awaiting_funding' => [
            'funds_confirmed' => ['funded', ['provider']],
            'payment_failed' => ['payment_failed', ['provider']],
            'cancel' => ['cancelled', ['seller', 'buyer']],
            'expire' => ['expired', ['worker']],
        ],
        'payment_failed' => [
            'cancel' => ['cancelled', ['seller', 'buyer']],
            'retry_allowed' => ['awaiting_funding', ['provider']],
            'expire' => ['expired', ['worker']],
        ],
        'funded' => [
            'dispatch' => ['shipped', ['seller']],
            'dispute' => ['disputed', ['buyer', 'seller']],
            'expire' => ['disputed', ['worker']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'shipped' => [
            'received' => ['received', ['buyer']],
            'dispute' => ['disputed', ['buyer', 'seller']],
            'expire' => ['disputed', ['worker']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'received' => [
            'request_release' => ['release_pending', ['finance']],
            'request_refund' => ['refund_pending', ['finance']],
            'dispute' => ['disputed', ['buyer', 'seller']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'disputed' => [
            'request_release' => ['release_pending', ['finance']],
            'request_refund' => ['refund_pending', ['finance']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'release_pending' => [
            'payout_confirmed' => ['released', ['provider']],
            'payout_failed' => ['disputed', ['provider']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'refund_pending' => [
            'refund_confirmed' => ['refunded', ['provider']],
            'refund_failed' => ['disputed', ['provider']],
            'chargeback' => ['charged_back', ['provider']],
        ],
        'released' => [
            'chargeback' => ['charged_back', ['provider']],
        ],
    ];

    $transitions = [];
    foreach ($edges[$status] ?? [] as $action => [$next, $allowedRoles]) {
        if (in_array($role, $allowedRoles, true)) {
            $transitions[$action] = $next;
        }
    }
    return $transitions;
}
