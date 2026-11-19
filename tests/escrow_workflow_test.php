<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/escrow_workflow.php';

function escrow_test_expect(bool $condition, string $context): void
{
    if (!$condition) {
        throw new RuntimeException('Escrow workflow assertion failed: ' . $context);
    }
}

function escrow_test_forbidden(string $status, string $role, string $action): void
{
    try {
        escrow_workflow_next($status, $role, $action);
    } catch (DomainException $error) {
        escrow_test_expect($error->getMessage() === 'Escrow action is not permitted for this state and role.', 'stable rejection');
        return;
    }
    throw new RuntimeException('Escrow workflow accepted a forbidden transition: ' . "$status/$role/$action");
}

$specification = [
    'seller' => [
        'awaiting_funding' => ['cancel' => 'cancelled'],
        'payment_failed' => ['cancel' => 'cancelled'],
        'funded' => ['dispatch' => 'shipped', 'dispute' => 'disputed'],
        'shipped' => ['dispute' => 'disputed'],
        'received' => ['dispute' => 'disputed'],
    ],
    'buyer' => [
        'awaiting_funding' => ['cancel' => 'cancelled'],
        'payment_failed' => ['cancel' => 'cancelled'],
        'funded' => ['dispute' => 'disputed'],
        'shipped' => ['received' => 'received', 'dispute' => 'disputed'],
        'received' => ['dispute' => 'disputed'],
    ],
    'proposer' => ['offered' => ['withdraw' => 'cancelled']],
    'recipient' => ['offered' => ['accept' => 'awaiting_funding', 'reject' => 'rejected', 'counter' => 'offered']],
    'finance' => [
        'received' => ['request_release' => 'release_pending', 'request_refund' => 'refund_pending'],
        'disputed' => ['request_release' => 'release_pending', 'request_refund' => 'refund_pending'],
    ],
    'provider' => [
        'awaiting_funding' => ['funds_confirmed' => 'funded', 'payment_failed' => 'payment_failed'],
        'payment_failed' => ['retry_allowed' => 'awaiting_funding'],
        'funded' => ['chargeback' => 'charged_back'],
        'shipped' => ['chargeback' => 'charged_back'],
        'received' => ['chargeback' => 'charged_back'],
        'disputed' => ['chargeback' => 'charged_back'],
        'release_pending' => ['payout_confirmed' => 'released', 'payout_failed' => 'disputed', 'chargeback' => 'charged_back'],
        'refund_pending' => ['refund_confirmed' => 'refunded', 'refund_failed' => 'disputed', 'chargeback' => 'charged_back'],
        'released' => ['chargeback' => 'charged_back'],
    ],
    'worker' => [
        'offered' => ['expire' => 'expired'],
        'awaiting_funding' => ['expire' => 'expired'],
        'payment_failed' => ['expire' => 'expired'],
        'funded' => ['expire' => 'disputed'],
        'shipped' => ['expire' => 'disputed'],
    ],
    'admin' => [],
    'Provider' => [],
    '' => [],
];
$statuses = ['offered', 'awaiting_funding', 'payment_failed', 'funded', 'shipped', 'received', 'disputed', 'release_pending',
    'refund_pending', 'released', 'rejected', 'cancelled', 'expired', 'refunded', 'charged_back', '', 'Released', 'pending'];
$actions = ['accept', 'reject', 'counter', 'withdraw', 'expire', 'funds_confirmed', 'payment_failed', 'cancel', 'retry_allowed',
    'dispatch', 'dispute', 'chargeback', 'received', 'request_release', 'request_refund', 'payout_confirmed', 'payout_failed',
    'refund_confirmed', 'refund_failed', '', 'Chargeback', 'force_release'];

$allowedCount = 0;
$deniedCount = 0;
foreach ($statuses as $status) {
    foreach ($specification as $role => $policy) {
        $expected = $policy[$status] ?? [];
        $expectedActions = array_keys($expected);
        $actualActions = escrow_workflow_actions($status, $role);
        sort($actualActions);
        sort($expectedActions);
        escrow_test_expect($actualActions === $expectedActions, "$status/$role allowed action list");
        foreach ($actions as $action) {
            if (isset($expected[$action])) {
                escrow_test_expect(escrow_workflow_next($status, $role, $action) === $expected[$action], "$status/$role/$action");
                ++$allowedCount;
            } else {
                escrow_test_forbidden($status, $role, $action);
                ++$deniedCount;
            }
        }
    }
}
escrow_test_expect($allowedCount === 39 && $allowedCount + $deniedCount === 3960, 'matrix fixture scope');
echo "PASS escrow state, role and action matrix ($allowedCount allowed, $deniedCount denied)\n";

$state = 'offered';
foreach ([['recipient', 'counter'], ['recipient', 'accept'], ['provider', 'payment_failed'], ['provider', 'retry_allowed'],
    ['provider', 'funds_confirmed'], ['seller', 'dispatch'], ['buyer', 'received'], ['finance', 'request_release'],
    ['provider', 'payout_failed'], ['finance', 'request_refund'], ['provider', 'refund_confirmed']] as [$role, $action]) {
    $state = escrow_workflow_next($state, $role, $action);
}
escrow_test_expect($state === 'refunded', 'failed funding and release recover through a refund');
escrow_test_forbidden($state, 'provider', 'refund_confirmed');
escrow_test_forbidden($state, 'finance', 'request_release');
echo "PASS escrow retries, disputed outcome and final-state replay denial\n";

$state = escrow_workflow_next('received', 'finance', 'request_release');
escrow_test_forbidden($state, 'finance', 'payout_confirmed');
$state = escrow_workflow_next($state, 'provider', 'payout_confirmed');
escrow_test_forbidden($state, 'provider', 'payout_confirmed');
escrow_test_forbidden($state, 'buyer', 'chargeback');
$state = escrow_workflow_next($state, 'provider', 'chargeback');
escrow_test_expect($state === 'charged_back' && escrow_workflow_actions($state, 'provider') === [], 'terminal chargeback');
echo "PASS escrow separate finance/provider roles and post-release chargeback\n";
