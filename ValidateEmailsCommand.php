<?php


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use \Egulias\EmailValidator\EmailValidator;
use \Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use \Egulias\EmailValidator\Validation\RFCValidation;
use \Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use \Egulias\EmailValidator\Validation\DNSCheckValidation;
use \Egulias\EmailValidator\Validation\SpoofCheckValidation;

class ValidateEmailsCommand extends Command
{
  protected static $defaultName = 'app:validate_emails';
  private $stats;
  private $progressBar;
  private $io;
  private $outputDir = 'output/';

  public function __construct($name = null)
  {
    parent::__construct($name);
    $this->setStats();
  }

  protected function configure()
  {
    $this
      ->setDescription('application for email validation')
      ->addArgument('fileName', InputArgument::REQUIRED, 'File name');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    $this->io = new SymfonyStyle($input, $output);
    $fileName = $input->getArgument('fileName');
    $data = $this->prepareAndCheckInputData($fileName);
    $this->io->comment('Email validation started');
    $totalEmailNumber = count($data);
    $this->stats['total_email'] = $totalEmailNumber;
    $this->setProgressBar($output, $totalEmailNumber);
    $this->validateEmails($data);
    $this->stats['finished_at'] = new DateTime();
    $this->prepareStatsFile();
    $this->progressBar->finish();
    $this->io->newLine();
    $this->io->success('Emails was successfully validated check the files');
  }

  private function prepareAndCheckInputData(string $fileName): array
  {
    if (!file_exists($fileName)) {
      $this->io->error('There is no file ' . $fileName . ' in project root directory');
      die;
    }
    $contents = file_get_contents($fileName);

    $data = str_getcsv($contents, "\n");
    if (!$data || !is_array($data)) {
      $this->io->error('Please check input file format');
      die;
    }
    return $data;
  }

  private function setStats()
  {
    if (!$this->stats) {
      $this->stats = [
        'started_at' => new DateTime(),
        'finished_at' => null,
        'total_time' => null,
        'total_email' => 0,
        'total_bad_email' => 0,
        'total_good_email' => 0,
      ];
    }
  }

  private function setProgressBar(OutputInterface $output, int $totalEmailNumber): void
  {
    if (!$this->progressBar) {
      $this->progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, $totalEmailNumber);
    }
  }

  private function validateEmails(array $emails): void
  {
    $validator = new EmailValidator();
    $multipleValidations = new MultipleValidationWithAnd([
      new RFCValidation(),
      new NoRFCWarningsValidation(),
      new DNSCheckValidation(),
      new SpoofCheckValidation(),
    ]);

    $goodEmails = fopen($this->outputDir . 'goodEmails.csv', 'b');
    $badEmails = fopen($this->outputDir . 'badEmails.csv', 'b');
    foreach ($emails as $email) {

      if ($validator->isValid($email, $multipleValidations)) {
        fputcsv($goodEmails, [$email], "\n");
        $this->stats['total_good_email'] += 1;
      } else {
        fputcsv($badEmails, [$email], "\n");
        $this->stats['total_bad_email'] += 1;
      }

      $this->progressBar->advance();
    }
    fclose($goodEmails);
    fclose($badEmails);
  }

  private function prepareStatsFile():void
  {
    $this->stats['finished_at'] = new DateTime();
    $summaryString = "This is email validator summary  \n \n";
    $summaryString .= "Validator started at: " . $this->stats['started_at']->format('Y-m-d H:i:s') . "\n";
    $summaryString .= "Validator finished at: " . $this->stats['finished_at']->format('Y-m-d H:i:s') . "\n";
    $summaryString .= "Total emails checked: " . $this->stats['total_email'] . "\n";
    $summaryString .= "Total bad emails: " . $this->stats['total_bad_email'] . "\n";
    $summaryString .= 'Total good emails: ' . $this->stats['total_good_email'] . "\n";

    file_put_contents($this->outputDir . 'summary.txt', $summaryString);
  }

}
