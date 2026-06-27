<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (!is_string($payload) || !shopSignalVerifyStripeSignature($payload, $signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid Stripe signature.']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || trim((string) ($event['id'] ?? '')) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid Stripe event.']);
    exit;
}

$pdo = Database::connect(shopSignalConfig());
if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable.']);
    exit;
}

try {
    shopSignalEnsureStripeSchema($pdo);
    $eventId = (string) $event['id'];
    $eventType = (string) ($event['type'] ?? 'unknown');
    $pdo->prepare('INSERT IGNORE INTO stripe_webhook_events (id, event_type) VALUES (:id, :event_type)')
        ->execute(['id' => $eventId, 'event_type' => $eventType]);
    $statusStatement = $pdo->prepare('SELECT processed_at FROM stripe_webhook_events WHERE id = :id LIMIT 1');
    $statusStatement->execute(['id' => $eventId]);
    if ($statusStatement->fetchColumn()) {
        echo json_encode(['ok' => true, 'duplicate' => true]);
        exit;
    }

    $object = $event['data']['object'] ?? [];
    if (!is_array($object)) {
        throw new RuntimeException('Stripe event object is missing.');
    }

    if ($eventType === 'checkout.session.completed') {
        $userId = shopSignalStripeUserId($pdo, $object);
        $paymentStatus = (string) ($object['payment_status'] ?? 'paid');
        if ($userId > 0) {
            shopSignalApplyStripeSubscription($pdo, $userId, [
                'id' => (string) ($object['subscription'] ?? ''),
                'customer' => (string) ($object['customer'] ?? ''),
                'status' => in_array($paymentStatus, ['paid', 'no_payment_required'], true) ? 'active' : 'incomplete',
            ]);
        }
    } elseif (in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated'], true)) {
        $userId = shopSignalStripeUserId($pdo, $object);
        if ($userId > 0) {
            shopSignalApplyStripeSubscription($pdo, $userId, $object);
        }
    } elseif ($eventType === 'customer.subscription.deleted') {
        $object['status'] = 'canceled';
        $userId = shopSignalStripeUserId($pdo, $object);
        if ($userId > 0) {
            shopSignalApplyStripeSubscription($pdo, $userId, $object);
        }
    } elseif (in_array($eventType, ['invoice.paid', 'invoice.payment_failed'], true)) {
        $subscriptionId = (string) ($object['subscription'] ?? $object['parent']['subscription_details']['subscription'] ?? '');
        $invoiceState = [
            'id' => $subscriptionId,
            'customer' => (string) ($object['customer'] ?? ''),
            'status' => $eventType === 'invoice.paid' ? 'active' : 'past_due',
        ];
        $userId = shopSignalStripeUserId($pdo, $invoiceState);
        if ($userId > 0) {
            shopSignalApplyStripeSubscription($pdo, $userId, $invoiceState);
        }
    }

    $pdo->prepare('UPDATE stripe_webhook_events SET processed_at = NOW() WHERE id = :id')->execute(['id' => $eventId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $exception) {
    error_log('ShopSignal Stripe webhook: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Webhook processing failed.']);
}
