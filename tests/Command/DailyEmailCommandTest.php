<?php

namespace App\Tests\Command;

use App\Command\DailyEmailCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class DailyEmailCommandTest extends TestCase
{
    private MockObject|MailerInterface $mailer;
    private MockObject|Environment $twig;
    private DailyEmailCommand $command;
    private string $tempRecipientsFile;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->tempRecipientsFile = sys_get_temp_dir() . '/recipients_' . uniqid() . '.json';

        file_put_contents($this->tempRecipientsFile, json_encode([
            ["name" => "Toto", "email" => "toto@test.com"],
            ["name" => "Tata", "email" => "tata@example.com"],
            ["name" => "Titi", "email" => "titi@example.com"],
        ]));

        $this->command = new DailyEmailCommand(
            $this->mailer,
            $this->twig,
            $this->tempRecipientsFile,
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempRecipientsFile)) {
            unlink($this->tempRecipientsFile);
        }
    }

    public function testEmailIsSentWithCorrectData(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'toto@test.com'
                    && $email->getFrom()[0]->getAddress() === 'your_email@example.com'
                    && $email->getSubject() === 'Your Daily Message'
                    && $email->getHtmlBody() === '<p>Hello, Toto!</p>';
            }));

        $this->twig->method('render')
            ->with('daily-spotify-email.html.twig', ['name' => 'Toto'])
            ->willReturn('<p>Hello, Toto!</p>');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Email sent to Toto', $output);
    }

    public function testRecipientsListIsUpdated(): void
    {
        $previousRecipientsList = json_decode(file_get_contents($this->tempRecipientsFile), true);
        $this->assertEquals([
            ["name" => "Toto", "email" => "toto@test.com"],
            ["name" => "Tata", "email" => "tata@example.com"],
            ["name" => "Titi", "email" => "titi@example.com"],
        ], $previousRecipientsList);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $newRecipientsList = json_decode(file_get_contents($this->tempRecipientsFile), true);
        $this->assertEquals([
            ["name" => "Tata", "email" => "tata@example.com"],
            ["name" => "Titi", "email" => "titi@example.com"],
            ["name" => "Toto", "email" => "toto@test.com"],
        ], $newRecipientsList);
    }

    public function testRecipientsListIsUnchangedIfMailerFails(): void
    {
        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException());

        $previousRecipientsList = json_decode(file_get_contents($this->tempRecipientsFile), true);
        $this->assertEquals([
            ["name" => "Toto", "email" => "toto@test.com"],
            ["name" => "Tata", "email" => "tata@example.com"],
            ["name" => "Titi", "email" => "titi@example.com"],
        ], $previousRecipientsList);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Could not send the email to toto@test.com', $output);

        $newRecipientsList = json_decode(file_get_contents($this->tempRecipientsFile), true);
        $this->assertEquals([
            ["name" => "Toto", "email" => "toto@test.com"],
            ["name" => "Tata", "email" => "tata@example.com"],
            ["name" => "Titi", "email" => "titi@example.com"],
        ], $newRecipientsList);
    }

    public function testCommandFailsIfNoRecipientsInList(): void
    {
        file_put_contents($this->tempRecipientsFile, json_encode([]));

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('No recipients found in the list.', $output);
        $this->assertEquals(Command::FAILURE, $exitCode);
    }
}