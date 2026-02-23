<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Hakam\MultiTenancyBundle\Command\DatabaseUrlParser;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use PHPUnit\Framework\TestCase;

class DatabaseUrlParserTest extends TestCase
{
    public function testParsesMysqlUrl(): void
    {
        $dto = DatabaseUrlParser::parse('mysql://root:secret@localhost:3306/main_db');

        $this->assertSame(DriverTypeEnum::MYSQL, $dto->driver);
        $this->assertSame('localhost', $dto->host);
        $this->assertSame(3306, $dto->port);
        $this->assertSame('main_db', $dto->dbname);
        $this->assertSame('root', $dto->user);
        $this->assertSame('secret', $dto->password);
    }

    public function testParsesPostgresUrl(): void
    {
        $dto = DatabaseUrlParser::parse('postgresql://pguser:pgpass@pg-host:5432/app_db');

        $this->assertSame(DriverTypeEnum::POSTGRES, $dto->driver);
        $this->assertSame('pg-host', $dto->host);
        $this->assertSame(5432, $dto->port);
        $this->assertSame('app_db', $dto->dbname);
    }

    public function testParsesSqliteUrl(): void
    {
        $dto = DatabaseUrlParser::parse('sqlite://localhost/var/data/app.sqlite');

        $this->assertSame(DriverTypeEnum::SQLITE, $dto->driver);
        $this->assertSame('var/data/app.sqlite', $dto->dbname);
    }

    public function testDefaultMysqlPortWhenAbsent(): void
    {
        $dto = DatabaseUrlParser::parse('mysql://root@localhost/db');
        $this->assertSame(3306, $dto->port);
    }

    public function testDefaultPostgresPortWhenAbsent(): void
    {
        $dto = DatabaseUrlParser::parse('postgresql://user@host/db');
        $this->assertSame(5432, $dto->port);
    }

    public function testNullPasswordWhenAbsent(): void
    {
        $dto = DatabaseUrlParser::parse('mysql://root@localhost/db');
        $this->assertNull($dto->password);
    }

    public function testUrlEncodedPasswordIsDecoded(): void
    {
        $dto = DatabaseUrlParser::parse('mysql://root:p%40ss%21word@localhost/db');
        $this->assertSame('p@ss!word', $dto->password);
    }

    public function testThrowsForUnparsableUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sqlite:///var/data/app.sqlite');

        DatabaseUrlParser::parse('sqlite:///var/data/app.sqlite');
    }
}
