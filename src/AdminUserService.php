<?php
declare(strict_types=1);

final class AdminUserService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $currentUserId
    ) {
    }

    public function update(array $input): string
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $role = in_array($input['role'] ?? '', ['user', 'admin'], true) ? (string) $input['role'] : 'user';
        $plan = in_array($input['plan'] ?? '', ['free', 'pro', 'enterprise'], true) ? (string) $input['plan'] : 'free';
        $status = in_array($input['status'] ?? '', ['active', 'disabled'], true) ? (string) $input['status'] : 'active';
        $verified = ($input['verified'] ?? '0') === '1';

        if ($userId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid name and email for the user.');
        }
        if ($userId === $this->currentUserId && ($status === 'disabled' || $role !== 'admin')) {
            throw new RuntimeException('You cannot disable or demote your own admin account.');
        }

        $existing = shopSignalFindUserByEmail($this->pdo, $email);
        if ($existing && (int) $existing['id'] !== $userId) {
            throw new RuntimeException('Another user already uses that email.');
        }

        $statement = $this->pdo->prepare('
            UPDATE users
            SET name = :name,
                email = :email,
                role = :role,
                plan = :plan,
                status = :status,
                email_verified_at = CASE WHEN :verified = 1 THEN COALESCE(email_verified_at, NOW()) ELSE NULL END
            WHERE id = :id
        ');
        $statement->execute([
            'name' => mb_substr($name, 0, 160),
            'email' => $email,
            'role' => $role,
            'plan' => $plan,
            'status' => $status,
            'verified' => $verified ? 1 : 0,
            'id' => $userId,
        ]);

        if ($statement->rowCount() === 0 && !$this->userExists($userId)) {
            throw new RuntimeException('User not found.');
        }

        return 'User updated.';
    }

    public function delete(int $userId): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User id is required.');
        }
        if ($userId === $this->currentUserId) {
            throw new RuntimeException('You cannot delete your own account.');
        }

        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);

        return 'User deleted.';
    }

    public function deleteNonAdmins(): string
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM users WHERE role <> \'admin\'');
            $this->pdo->exec('DELETE FROM pending_registrations');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return 'All non-admin users and pending registrations were deleted.';
    }

    public function deletePendingRegistration(int $pendingId): string
    {
        if ($pendingId <= 0) {
            throw new InvalidArgumentException('Pending registration id is required.');
        }

        $this->pdo->prepare('DELETE FROM pending_registrations WHERE id = :id')
            ->execute(['id' => $pendingId]);

        return 'Pending registration deleted.';
    }

    public function decideProRequest(int $requestId, string $decision): string
    {
        shopSignalDecideProRequest($this->pdo, $requestId, $decision, $this->currentUserId);
        return $decision === 'approved' ? 'Pro access approved.' : 'Pro access request rejected.';
    }

    private function userExists(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        return (bool) $statement->fetchColumn();
    }
}
