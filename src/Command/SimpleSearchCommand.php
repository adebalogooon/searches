<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;


class SimpleSearchCommand extends Command
{
    protected $max = 1000;

    protected $files = [];

    protected $print;

    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:search';

    /**
     * Configure command application
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Simple Search')
            ->setHelp('This command allows search and rank scores based on given input')
            ->addArgument('directory', InputArgument::REQUIRED, 'The file name to search is required');
    }

    /**
     * Executes the command
     * 
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Simple Search');
        $directory = $input->getArgument('directory');

        $directory      = trim($directory, '/');
        $filePath       = __DIR__ . '/../../var/search/' . $directory . '/';
        $this->print    = $output;

        if (file_exists($filePath)) {
            $fi = new \FilesystemIterator($filePath, \FilesystemIterator::SKIP_DOTS);
            $output->writeln(iterator_count($fi) . " files read in " . $directory);
            $this->scanDirectory($filePath, $output);
            $this->askQuestions($input, $output);
        } else {
            $output->writeln('Directory: ' . $directory . ' cannot be found');
        }
    }

    /**
     * Scans all the files in the given directory
     * 
     * @return void
     */
    protected function scanDirectory($directory): void
    {
        $files = scandir($directory);

        foreach($files as  $file) {
            if ($file !== '.' && $file !== '..' && !is_dir($file)) {
                $fileContent = file_get_contents($directory . $file);
                $this->files[$file] = $fileContent;
            }
        }
    }

    /**
     * Input search words and get the Ranking in percentage
     * 
     * @return void
     */
    protected function askQuestions($input): void
    {
        $question   = new Question('Enter word(s) to search: ');
        $helper     = $this->getHelper('question');
        for ($i=0; $i < $this->max; $i++) {
            $words = $helper->ask($input, $this->print, $question);
            $this->print->writeln($words);
            $this->getRanking($words);
        }
    }


    /**
     * Print the ranking based on the number of word occurences
     * 
     * @param string $words
     * @return void
     */
    protected function getRanking($words)
    {
        $files = [];

        foreach ($this->files as $fileName => $content) {
            if (empty($content)) {
                continue;
            }
            $content    = strtolower($content);
            $words      = strtolower($words);

            if (preg_match("/\b($words)\b/", $content)) {
                $files[$fileName] = 100;
            } else {
                //Explode and rank what is found
                $wordsArray = explode(' ', $words);
                $count      = 0;
                foreach ($wordsArray as $word) {
                    if (preg_match("/\b($word)\b/", $content)) {
                        $count++;
                    }
                }
                $totalWords = count($wordsArray);
                if ($count > 0) {
                    $perc = $count / $totalWords * 100;
                    $files[$fileName] = ceil($perc);
                }
            }
        }

        if (!empty($files)) {
            arsort($files);
            foreach($files as $file => $perc) {
                $this->print->writeln($file . ' : ' . $perc . '%');
            }
        } else {
            $this->print->writeln("No matches found");
        }
    }

}
