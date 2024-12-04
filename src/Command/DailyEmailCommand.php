<?php

namespace App\Command;

use App\Entity\Recipient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(name: 'app:daily-email')]
class DailyEmailCommand extends Command
{
    const COMMAND_NAME = 'app:daily-email';
    const RECIPIENTS_FILE_PATH = __DIR__ . '/../../config/recipients.json';

    private MailerInterface $mailer;
    private Environment $twig;
    private string $recipientsFilePath;

    public function __construct(MailerInterface $mailer, Environment $twig, ?string $recipientsFilePath = null)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->recipientsFilePath = $recipientsFilePath ?? self::RECIPIENTS_FILE_PATH;
    }

    protected function configure(): void
    {
        $this->setDescription('Sends an email to the next recipient in the list.');
    }

    // TODO cron
    // TODO config email
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipients = json_decode(file_get_contents($this->recipientsFilePath), true);
        if (empty($recipients)) {
            $output->writeln('No recipients found in the list.');
            return Command::FAILURE;
        }

        $recipient = array_shift($recipients);
        $recipient = new Recipient($recipient['name'], $recipient['email']);
        $email = $this->createEmail($recipient);

        try {
            $this->mailer->send($email);
        } catch (\Exception $exception) {
            $output->writeln(sprintf(
                'Could not send the email to %s. Error: %s.',
                $recipient->getEmail(),
                $exception->getMessage()
            ));
            $this->prependRecipientToStartOfList($recipients, $recipient);
            return Command::FAILURE;
        }

        $output->writeln('Email sent to ' . $recipient->getFirstName());
        $this->appendRecipientToEndOfList($recipients, $recipient);
        return Command::SUCCESS;
    }

    private function createEmail(Recipient $recipient): Email
    {
        $htmlContent = $this->twig->render('daily-spotify-email.html.twig', [
            'name' => $recipient->getFirstName(),
        ]);

        return (new Email())
            ->from('your_email@example.com') // TODO
            ->to($recipient->getEmail())
            ->subject('Your Daily Message')
            ->html($htmlContent);
    }

    private function appendRecipientToEndOfList(array $recipients, Recipient $currentRecipient): void
    {
        $recipients[] = [
            "name" => $currentRecipient->getFirstName(),
            "email" => $currentRecipient->getEmail(),
        ];
        file_put_contents($this->recipientsFilePath, json_encode($recipients, JSON_PRETTY_PRINT));
    }

    private function prependRecipientToStartOfList(array $recipients, Recipient $currentRecipient): void
    {
        array_unshift($recipients, [
            "name" => $currentRecipient->getFirstName(),
            "email" => $currentRecipient->getEmail(),
        ]);
        file_put_contents($this->recipientsFilePath, json_encode($recipients, JSON_PRETTY_PRINT));
    }
}
