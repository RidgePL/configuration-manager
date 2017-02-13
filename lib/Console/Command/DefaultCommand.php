<?php

namespace Magium\Configuration\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DefaultCommand extends Command
{
    const COMMAND = 'magium:configuration:init';

    protected function configure()
    {
        $this->setName(self::COMMAND)
            ->setHelp('Initializes Magium Configuration')
            ->setDescription('Initializes Magium Configuration by creating the default magium-configuration.xml and '
                . 'contexts.xml files');
    }

    protected function getPossibleLocations()
    {
        $path = __DIR__;
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $path = preg_replace('@/lib/.+@', '', $path);
        $paths = explode('/', $path);
        array_shift($paths); // Probably should not be in the root path...
        $configurationLocation = realpath(DIRECTORY_SEPARATOR);
        $possibleLocations = [];
        $foundVendor = false;
        foreach ($paths as $path) {
            $configurationLocation .= DIRECTORY_SEPARATOR . $path;
            $configurationLocation = realpath($configurationLocation);
            $file = $configurationLocation . DIRECTORY_SEPARATOR . 'magium-configuration.xml';

            if (file_exists($file)) {
                return false;
            }
            if (basename($path) == 'vendor') {
                $foundVendor = true;
            }
            if (!$foundVendor) {
                $possibleLocations[] = $file;
            }
        }
        return $possibleLocations;
    }

    protected function writeMagiumConfigurationFile($file)
    {
        file_put_contents($file, <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration">
    <persistenceConfiguration>
        <driver></driver>
        <database></database>
    </persistenceConfiguration>
    <contextConfigurationFile file="contexts.xml"/>
    <cache>
        <adapter>filesystem</adapter>
        <options>
            <cache_dir>/tmp</cache_dir>
        </options>
    </cache>
</magium>
XML
        );
    }

    protected function getContextFileFromConfigPath($configPath)
    {
        $basePath = dirname($configPath);
        $contextPath = $basePath . DIRECTORY_SEPARATOR . 'contexts.xml';
        return $contextPath;
    }

    protected function writeContextFileXml($contextPath)
    {
        file_put_contents($contextPath, <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<defaultContext xmlns="http://www.magiumlib.com/ConfigurationContext">
    <context id="production" title="Production" />
    <context id="development" title="Development" />
</defaultContext>
XML
        );
    }

    protected function executeChoices(array $possibleLocations, InputInterface $input, OutputInterface $output)
    {

        $result = $this->askConfigurationQuestion($possibleLocations, $input, $output);
        $this->writeMagiumConfigurationFile($result);
        $output->writeln('Wrote XML configuration file to: ' . $result);

        $contextPath = $this->getContextFileFromConfigPath($result);
        if (!file_exists($contextPath)) {
            $result = $this->askContextFileQuestion($input, $output, $contextPath);
            if ($result) {
                $this->writeContextFileXml($contextPath);
            }
        }
    }

    protected function askConfigurationQuestion(array $possibleLocations, InputInterface $input, OutputInterface $output)
    {
        $question = new ChoiceQuestion(
            'Could not find a magium-configuration.xml file.  Where would you like me to put it?',
            $possibleLocations
        );
        $result = $this->getHelper('question')->ask($input, $output, $question);
        return $result;
    }


    protected function askContextFileQuestion(InputInterface $input, OutputInterface $output, $contextPath)
    {
        $question = new ConfirmationQuestion(sprintf('The context file %s does not exist next to the magium-configuration.xml file.  Create it? ', $contextPath));
        $result = $this->getHelper('question')->ask($input, $output, $question);
        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $possibleLocations = $this->getPossibleLocations();
        if ($possibleLocations === false) {
            $command = $this->getApplication()->find('list');
            $command->run($input, $output);
            return;
        }

        $this->executeChoices($possibleLocations, $input, $output);
    }


}
