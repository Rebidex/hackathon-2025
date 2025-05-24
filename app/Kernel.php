<?php

declare(strict_types=1);

namespace App;

//use Slim\Csrf\Guard;
//use Slim\Psr7\Factory\ResponseFactory;

use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\AuthService;
use App\Infrastructure\Persistence\PdoExpenseRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use function DI\autowire;
use function DI\factory;

class Kernel
{
    public static function createApp(): App
    {
        // Configure the DI container builder and build the DI container
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);  // Enable autowiring explicitly

        $builder->addDefinitions([
            // Define a factory for the Monolog logger with a stream handler that writes to var/app.log
            LoggerInterface::class => function () {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler(__DIR__ . '/../var/app.log', Level::Debug));

                return $logger;
            },

            // Define a factory for Twig view renderer
            Twig::class => function () {
                $twig = Twig::create(__DIR__.'/../templates', ['cache' => false]);
                return $twig;
            },

            // Databse config with error handling
            PDO::class => factory(function () {
                $databasePath = __DIR__ . '/../../database/db.sqlite';

                if (!file_exists(dirname($databasePath))) {
                    mkdir(dirname($databasePath), 0775, true);
                }
                try {
                    $pdo = new PDO('sqlite:' . $databasePath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                    //If there is no users table, execute Create table
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS users (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            username TEXT NOT NULL UNIQUE,
                            password_hash TEXT NOT NULL,
                            created_at DATETIME NOT NULL
                        )   
                    ");
                    return $pdo;
                } catch (\PDOException $e) {
                    throw new \RuntimeException(
                        "Failed to connect to the database at {$databasePath}: " . $e->getMessage()
                    );
                }
            }),

            // Map interfaces to concrete implementations
            UserRepositoryInterface::class => autowire(PdoUserRepository::class),
            ExpenseRepositoryInterface::class => autowire(PdoExpenseRepository::class),

            AuthService::class => autowire()
                ->constructorParameter('users', \DI\get(UserRepositoryInterface::class)),
        ]);
        $container = $builder->build();

        //Initialize session
        self::initializeSession();

        // Create an application instance and configure
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Add only TwigMiddleware, CSRF middleware removed
        $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

        (require __DIR__ . '/../config/settings.php')($app);
        (require __DIR__ . '/../config/routes.php')($app);

        $twig = $container->get(Twig::class);
        $twig->getEnvironment()->addGlobal('currentUserId', $_SESSION['user_id'] ?? null);
        $twig->getEnvironment()->addGlobal('currentUsername', $_SESSION['username'] ?? null);

        return $app;
    }

    private static function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'name' => 'expense_tracker',
                'cookie_lifetime' => 86400,
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true
            ]);
            // Log session info for debugging
            error_log("Session ID: " . session_id());
        }
    }

}