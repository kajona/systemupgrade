<?php

class KajonaUpgrade
{


    public function main()
    {
        if (!is_dir(__DIR__."/core")) {
            echo "/core directory not found, aborting\n";
        }

        //check some permissions
        if (!$this->checkPermissions()) {
            return;
        }

        //download each phar and move to core
        foreach ($this->getPharList() as $strOnePhar) {
            $this->updatePhar($strOnePhar);
        }

        //create / call installer
        if (!$this->createInstallerFile()) {
            echo "Failed to create installer file, please create manually and call the installer\n";
        }

        echo "Download succeeded, please continue with the installer\n";
        echo "<a href='installer.php?step=install'>Open Installer</a>\n";

    }

    private function updatePhar($strPhar)
    {
        echo "Updating phar <b>".$strPhar."</b>\n";

        //fetch the local version
        if (is_file("phar://".__DIR__."/core/".$strPhar."/metadata.xml")) {
            $objMedatadata = new SimpleXMLElement(file_get_contents("phar://".__DIR__."/core/".$strPhar."/metadata.xml"));

            $strModule = $objMedatadata->title;
            $strVersion = $objMedatadata->version;

            echo "  Local version: {$strModule}, V{$strVersion}\n";

            //fetch the remote version
            $strRemote = file_get_contents("https://www.kajona.de/xml.php?module=packageserver&action=list&protocolversion=5&title=".urlencode($strModule));
            if (empty($strRemote)) {
               echo "  Failed to load remote version\n";
               return;
            }

            $arrRemote = json_decode($strRemote, true);
            if(isset($arrRemote["numberOfTotalItems"]) && isset($arrRemote["numberOfTotalItems"]) == 1) {
                $strRemoteVersion = $arrRemote["items"][0]["version"];
                $strSystemid = $arrRemote["items"][0]["systemid"];

                echo "  Remote version: V{$strRemoteVersion}\n";
                if(version_compare($strVersion, $strRemoteVersion, "<")) {
                    echo "  Downloading new phar module\n";
                    ob_flush();
                    flush();
                    file_put_contents(__DIR__."/project/temp/".$strPhar, file_get_contents("https://www.kajona.de/download.php?systemid=".$strSystemid));

                    if(is_file(__DIR__."/project/temp/".$strPhar)) {
                        //get filesize
                        $intBytes = filesize(__DIR__."/project/temp/".$strPhar);
                        $intSize = number_format($intBytes / 1024 / 1024, 2);

                        echo "  Downloaded {$intSize}MB, moving new phar to /core replacing old version\n";
                        rename(__DIR__."/project/temp/".$strPhar, __DIR__."/core/".$strPhar);

                    } else {
                        echo "  Download failed\n";

                    }
                }
                else {
                    echo "  Skipping, no new version found\n";
                }
            }


        }
        else {
            echo "  Skipping, no valid Kajona phar\n";
        }
        echo "\n";

        ob_flush();
        flush();
    }

    private function getPharList()
    {
        $arrContent = scandir(__DIR__."/core");

        $arrContent = array_filter($arrContent, function ($strFilename) {
            return strpos($strFilename, ".phar") !== false;
        });

        return $arrContent;
    }

    private function checkPermissions()
    {
        echo "Checking write permissions on selected files and folders\n\n";
        if (!is_file(__DIR__."/installer.php") && !is_writable(__DIR__)) {
            echo "  Directory ".__DIR__." is not writable, aborting!\n";
            return false;
        }

        if (!is_writable(__DIR__."/project/temp")) {
            echo "  Directory ".__DIR__."/project/temp is no writable, aborting!\n";
            return false;
        }

        $bitAllPharsWritable = true;
        foreach ($this->getPharList() as $strOnePhar) {
            if (!is_writable(__DIR__."/core/".$strOnePhar)) {
                $bitAllPharsWritable = false;
                echo "Phar ".__DIR__."/core/".$strOnePhar." not writable, aborting\n";
            }
        }

        return $bitAllPharsWritable;
    }




    private function createInstallerFile()
    {

        if (!is_file(__DIR__."/installer.php")) {
            $strInstaller = <<<PHP
<?php
/*"******************************************************************************************************
*   (c) 2007-2016 by Kajona, www.kajona.de                                                              *
*       Published under the GNU LGPL v2.1, see /system/licence_lgpl.txt                                 *
********************************************************************************************************/


if(is_dir("./core/module_system/")) {
    require_once './core/module_system/bootstrap.php';
}
else {
    require_once 'phar://'.__DIR__.'/core/module_system.phar/bootstrap.php';
}

if(is_dir("./core/module_installer/")) {
    require_once './core/module_installer/installer.php';
}
else {
    require_once 'phar://'.__DIR__.'/core/module_installer.phar/installer.php';
}

PHP;

            return file_put_contents(__DIR__."/installer.php", $strInstaller);

        }

        return true;
    }

}

echo "<pre>";
echo "\n\n<b>Kajona upgrade helper</b>\n\n";
$objUpdater = new KajonaUpgrade();
$objUpdater->main();
echo "</pre>";
