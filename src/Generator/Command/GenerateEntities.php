<?php

namespace SimpleDAO\Generator\Command;

use Nette\Utils\FileSystem;
use PDO;
use SimpleDAO\Generator\EntityGenerator;
use SimpleDAO\Utils\Inflector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Description of GenerateEntities
 *
 * @author cevantime
 */
class GenerateEntities extends Command
{

    const CONFIG_FILE_NAME = "simple-dao-generator.json";

    private static $QUESTION_MAPPING = [
        'dbms' => [
            'Database dbms',
            'mysql'
        ],
        'host' => [
            'Database host',
            '127.0.0.1'
        ],
        'dbname' => [
            'Database name'
        ],
        'user' => [
            'Database user',
            'root'
        ],
        'password' => [
            'Database password',
            ''
        ],
        'charset' => [
            'Charset',
            'utf8'
        ],
        'namespace' => [
            'Namespace',
            ''
        ],
        'folder' => [
            'Folder',
            __DIR__ . '/../../../src'
        ]
    ];

    protected function configure()
    {
        $this->setName('generate:classes')
                ->setDescription('(Re)generates all classes (entities and dao) associated with a database connection')
                ->addOption('dbms', 'd', InputOption::VALUE_REQUIRED, 'The dbms used (mysql, postgre...)', 'mysql')
                ->addOption('host', 'o', InputOption::VALUE_REQUIRED, 'The ip of the host', '127.0.0.1')
                ->addOption('dbname', 'b', InputOption::VALUE_REQUIRED, 'The name of the database')
                ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'The username of the database user')
                ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'The password of the user')
                ->addOption('charset', 'c', InputOption::VALUE_REQUIRED, 'The password of the user')
                ->addOption('namespace', 'a', InputOption::VALUE_REQUIRED, 'The password of the user')
                ->addOption('folder', 'f', InputOption::VALUE_REQUIRED, 'The password of the user')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (file_exists(self::CONFIG_FILE_NAME)) {
            $configs = json_decode(file_get_contents(self::CONFIG_FILE_NAME));
            $config = $configs['dsn'];
        } else {
            $configs = [];
            $config = [];
        }

        $helperQuestion = $this->getHelper('question');

        $questionMapping = self::$QUESTION_MAPPING;

        $options = $input->getOptions();

        foreach ($options as $key => $opt) {
            if (!isset($config[$key])) {

                if (!isset($questionMapping[$key])) {
                    continue;
                }

                if (isset($questionMapping[$key][1])) {
                    $question = new Question($questionMapping[$key][0] . " ({$questionMapping[$key][1]}) : ", $questionMapping[$key][1]);
                } else {
                    $question = new Question($questionMapping[$key][0] . " : ");
                }

                $resp = $helperQuestion->ask($input, $output, $question);

                $input->setOption($key, $resp);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = $input->getOptions();

        $output->writeln('Writing entities...');

        if (!$options['dbname']) {
            $output->writeln('No database selected !');
            return;
        }
        
        if ($options['dbms'] != 'mysql') {
            $output->writeln('Only mysql is supported !');
            return;
        }

        $pdo = new PDO("mysql:host:{$options['host']};dbname:{$options['dbname']};charset:{$options['charset']}", $options['user'], $options['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $stmtTableInfos = $pdo->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
        
        $stmtTableInfos->bindValue(1, $options['dbname']);
        
        $stmtTableInfos->execute();
        
        $infos = $stmtTableInfos->fetchAll(PDO::FETCH_ASSOC);
        
        $entityFolder = $options['folder'].'/Entity';
        FileSystem::delete($entityFolder);
        
        $daoFolder = $options['folder'].'/DAO';
        FileSystem::delete($daoFolder);
        
        foreach($infos as $info) {
            $entityGenerator = new EntityGenerator($pdo, $info['TABLE_NAME'], $options);
            $codeStr = $entityGenerator->generate();
            
            FileSystem::write($entityFolder.'/'.Inflector::camelize($info['TABLE_NAME']).'.php',$codeStr);
            
        }
    }

}
