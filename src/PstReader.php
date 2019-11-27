<?php


namespace Cronox\PstReader;


use PhpMimeMailParser\Parser;

class PstReader
{

    protected $sourcePstFilePath = null;
    protected $destinationPstDirPath = null;
    protected $parsedEmails = [];
    protected $parsedAllEmails = [];
    protected $replaceUnpacked = false;

    /**
     * PstReader constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        if (false === function_exists('exec')) {
            throw new \Exception('exec() is disabled!');
        }

        @exec('which readpst', $output, $exitCode);

        if (true === (bool)$exitCode) {
            throw new \Exception('readpst is not installed!');
        }
    }

    /**
     * @return null|string
     */
    public function getSourcePstFilePath()
    {
        return $this->sourcePstFilePath;
    }

    /**
     * @param string $sourcePstFilePath
     * @return object
     * @throws \Exception
     */
    public function setSourcePstFilePath(string $sourcePstFilePath): object
    {
        if (false === file_exists($sourcePstFilePath)) {
            throw new \Exception($sourcePstFilePath . ' not exists!');
        }

        $this->sourcePstFilePath = $sourcePstFilePath;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDestinationPstDirPath()
    {
        return $this->destinationPstDirPath;
    }

    /**
     * @param string $destinationPstDirPath
     * @return $this
     * @throws \Exception
     */
    public function setDestinationPstDirPath(string $destinationPstDirPath): object
    {
        $isDir = is_dir($destinationPstDirPath);
        if (false === $isDir) {
            $isSuccess = @mkdir($destinationPstDirPath, 0777, true);
            if (false === $isSuccess) {
                throw new \Exception('Can not create directory ' . $destinationPstDirPath . ' !');
            }
        }
        $this->destinationPstDirPath = $destinationPstDirPath;
        return $this;
    }

    /**
     * @return array
     */
    public function getParsedEmails()
    {
        return $this->parsedEmails;
    }

    /**
     * @param bool $replaceUnpacked
     * @return $this
     */
    public function setReplaceUnpacked(bool $replaceUnpacked): object
    {
        $this->replaceUnpacked = $replaceUnpacked;
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function unpackPstFile(): object
    {
        $sourcePstFilePath = $this->sourcePstFilePath;
        $destinationPstDirPath = $this->destinationPstDirPath;

        $cmd = sprintf('%sreadpst -8 -S -o "%s" "%s"',
            (true === $this->replaceUnpacked ? sprintf('rm -r "%1$s" && mkdir -p "%1$s" && ', $destinationPstDirPath) : ''),
            $destinationPstDirPath,
            $sourcePstFilePath
        );

        exec($cmd, $output, $exitCode);
        $outputString = implode("\n", $output);

        if (true === (bool)$exitCode) {
            throw new \Exception('Cant unpack PST file:' . PHP_EOL . $outputString);
        }

        $this->fetchEmails();

        return $this;
    }

    protected function fetchEmails(): void
    {
        $destinationPstDirPath = $this->destinationPstDirPath;
        $this->parsedEmails = [];
        $this->fetchEmailsFromDir($destinationPstDirPath);
    }


    /**
     * @param string $dirPath
     * @throws \Exception
     */
    private function fetchEmailsFromDir(string $dirPath): void
    {
        $dir = scandir($dirPath);
        $dir = $this->removeDots($dir);

        $folderName = str_replace($this->destinationPstDirPath, '', $dirPath);
        if (empty($folderName)) {
            $folderName = '/';
        }

        if (!isset($this->parsedEmails['folders'][$folderName])) {
            $this->parsedEmails['folders'][$folderName] = [
                'emails' => []
            ];
        }

        if (empty($dir)) {
            return;
        }

        foreach ($dir as $item) {
            $elem = sprintf('%s/%s', $dirPath, $item);
            if (is_dir($elem)) {
                $this->fetchEmailsFromDir($elem);
                continue;
            }

            if (false === is_numeric($item)) {
                continue;
            }

            $emailParser = new Parser();
            $emailParser->setPath($elem);

            $this->addEmail($folderName, $item, $emailParser);

            $this->fetchAttachmentsFromDir($item, $dirPath, $folderName);
        }
    }

    private function fetchAttachmentsFromDir($emailIndex, $dirPath, $folderName)
    {
        $dir = scandir($dirPath);
        $dir = $this->removeDots($dir);

        $matches = preg_grep(sprintf('/^%d-.*/i', $emailIndex), $dir);
        foreach ($matches as $match) {
            $this->addEmailAttachment($folderName, $emailIndex, [
                'fileName' => $match,
                'filePath' => sprintf('%s/%s', $dirPath, $match)
            ]);
        }

    }

    /**
     * @param string $folderName
     * @param string $index
     * @param Parser $emailObject
     * @throws \Exception
     */
    private function addEmail(string $folderName, string $index, Parser $emailObject): void
    {
        if (isset($this->parsedEmails['folders'][$folderName]['emails'][$index]['email'])) {
            throw new \Exception('#' . $index . ' is exists!');
        }

        $this->parsedEmails['folders'][$folderName]['emails'][$index]['email'] = $emailObject;
        $this->parsedAllEmails[$index]['email'] = $emailObject;
    }

    /**
     * @param string $folderName
     * @param string $index
     * @param array $attachment
     */
    private function addEmailAttachment(string $folderName, string $index, array $attachment): void
    {
        $this->parsedEmails['folders'][$folderName]['emails'][$index]['attachments'][] = $attachment;
        $this->parsedAllEmails[$index]['attachments'][] = $attachment;
    }

    /**
     * @param array $array
     * @return array
     */
    private function removeDots(array $array): array
    {
        $key = array_search('.', $array, true);
        if ($key !== false) {
            unset($array[$key]);
        }
        $key = array_search('..', $array, true);
        if ($key !== false) {
            unset($array[$key]);
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getParsedAllEmails(): array
    {
        ksort($this->parsedAllEmails);
        return $this->parsedAllEmails;
    }


}
