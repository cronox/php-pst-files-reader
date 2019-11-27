## Installation

Install package `pst-utils` via `apt`
```
apt install pst-utils
```

add this line to your `composer.json` file:
```json
"cronox/php-pst-files-reader": "^1.0"
```
and run 
```sh
composer update
```

or run
```sh
composer require cronox/serial-php
```

## A Simple Example

```php
$sourcePstFilePath = "/my-psts/Outlook.pst";
$destinationPstDirPath = "/my-psts/unpack-here";

try {
    $parsedEmails = $PstReader
         ->setSourcePstFilePath($sourcePstFilePath)
         ->setDestinationPstDirPath($destinationPstDirPath)
         ->setReplaceUnpacked(true)
         ->unpackPstFile()
         ->getParsedAllEmails();
} catch (\Exception $exception) {
    throw $exception;
}
```