<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password): User
    {   
        if(strlen($username) < 4) {
            throw new RuntimeException('Username must be at least 4 characters.');
        }

        if(!preg_match('/^(?=.*\d).{8,}$/', $password)) {
            throw new RuntimeException('Password must be at least 8 characters and contain a number.');
        }

        if ($this->users->findByUsername($username)) {
            throw new RuntimeException('Username already taken!');
        }

        $hashPassword = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(
            id: null,
            username: $username,
            passwordHash: $hashPassword,
            createdAt: new DateTimeImmutable()
        );

        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return true;
    }
}
