<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, CQFDev <franck@cqfdev.fr>
 * Date: 25/09/2019 16:47
 */

namespace Translation\Dumper;

use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

class PoFileDumperWithComments extends PoFileDumper
{
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = array())
    {
        $output = 'msgid ""'."\n";
        $output .= 'msgstr ""'."\n";
        $output .= '"Content-Type: text/plain; charset=UTF-8\n"'."\n";
        $output .= '"Content-Transfer-Encoding: 8bit\n"'."\n";
        $output .= '"Language: '.$messages->getLocale().'\n"'."\n";
        $output .= "\n";

        if (isset($options['metadata'])) {
            $metaData = $options['metadata'];
        } else {
            $metaData = [];
        }

        $newLine = false;
        foreach ($messages->all($domain) as $source => $target) {
            if ($newLine) {
                $output .= "\n";
            } else {
                $newLine = true;
            }

            if (isset($metaData[$source])) {
                $output .= sprintf('#: %s'."\n", $metaData[$source]);
            }

            $output .= sprintf('msgid "%s"'."\n", $this->escape($source));
            $output .= sprintf('msgstr "%s"', $this->escape($target));
        }

        return $output;
    }

    private function escape($str)
    {
        return addcslashes($str, "\0..\37\42\134");
    }
}
