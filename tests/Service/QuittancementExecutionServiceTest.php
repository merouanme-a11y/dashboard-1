<?php

namespace App\Tests\Service;

use App\Service\QuittancementExecutionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class QuittancementExecutionServiceTest extends TestCase
{
    public function testExecuteSqlSectionsRunsBlocksSequentially(): void
    {
        $calls = [];
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->exactly(3))
            ->method('exec')
            ->willReturnCallback(function (string $sql) use (&$calls): int {
                $calls[] = $sql;

                return 1;
            });
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');

        $service = new class($pdo) extends QuittancementExecutionService {
            public function __construct(private PDO $pdo)
            {
                parent::__construct('host', 5432, 'db', 'user', 'password');
            }

            protected function createConnection(): PDO
            {
                return $this->pdo;
            }
        };

        $result = $service->executeSqlSections([
            'rattrapage' => 'select 1;',
            'quittancement' => 'select 2;',
            'sante_collective' => 'select 3;',
        ]);

        self::assertSame(['select 1;', 'select 2;', 'select 3;'], $calls);
        self::assertSame([
            'Bloc 1 - Rattrapage',
            'Bloc 2 - Quittancement',
            'Bloc 3 - Sante collective',
        ], $result['executedBlocks']);
        self::assertTrue($result['committed']);
    }

    public function testExecuteSqlSectionsRollsBackWhenOneBlockFails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->exactly(2))
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                if ($sql === 'select 2;') {
                    throw new \RuntimeException('boom');
                }

                return 1;
            });
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);
        $pdo->expects($this->never())->method('commit');

        $service = new class($pdo) extends QuittancementExecutionService {
            public function __construct(private PDO $pdo)
            {
                parent::__construct('host', 5432, 'db', 'user', 'password');
            }

            protected function createConnection(): PDO
            {
                return $this->pdo;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bloc 2 - Quittancement a echoue: boom');

        $service->executeSqlSections([
            'rattrapage' => 'select 1;',
            'quittancement' => 'select 2;',
            'sante_collective' => 'select 3;',
        ]);
    }
}
