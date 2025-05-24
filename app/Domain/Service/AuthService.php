<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{

    private const MIN_USERNAME_LENGHT = 4;
    private const MIN_PASSWORD_LENGTH = 8;
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function registerNewUser(string $username, string $password, string $passwordConfirm): array
    {
        $errorsValidation = $this -> validateInputForRegistration($username, $password, $passwordConfirm);
        if(!empty($errorsValidation))
        {
            return ['success' => false, 'errors' => $errorsValidation];
        }
        if ($this->usernameThatAlreadyExists($username))
        {
            return ['success' => false, 'errors' => ['username' => 'This username is already taken. Please choose another.']];
        }

        $newUser = $this->createUserWithAHashedPassword($username, $password);
        $this->users->save($newUser);
        return ['success' => true,'user' => $newUser];
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);
         if($user == null || !password_verify($password, $user->passwordHash))
         {
             return false;
         }
         $this->startSecureUserSession($user);
        return true;
    }
    private function usernameThatAlreadyExists(string $username): bool
    {
        return $this->users->findByUsername($username) !== null;
    }
    private function validateInputForRegistration(string $username, string $password, string $passwordConfirm) : array
    {
        $errors = [];
        if(strlen($username) < self::MIN_USERNAME_LENGHT)
        {
            $errors['username'] = sprintf('Your username has to be at least %d characters long', self::MIN_USERNAME_LENGHT);
        }
        if(strlen($password) < self::MIN_PASSWORD_LENGTH)
        {
            $errors['password'] = sprintf('Your password has to be at least %d characters long', self::MIN_PASSWORD_LENGTH);
        }
        if(!preg_match('/[0-9]/', $password))
        {
            $errors['password'] = 'Password must contain at least 1 number';
        }
        if($password !== $passwordConfirm)
        {
            $errors['password'] = 'Password do not match';
        }

        return $errors;
    }
    private function createUserWithAHashedPassword(string $username, string $password):User
    {
        return new User(
            null,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            new \DateTimeImmutable()
        );
    }
    private function startSecureUserSession(User $user) : void
    {
        $this->startSessionIfNeeded();
        $this->regenerateSessionIdForSecurity();
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
    }

    private function startSessionIfNeeded(): void
    {
        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }
    }
    private function regenerateSessionIdForSecurity(): void
    {
        session_regenerate_id(true);
    }

}
