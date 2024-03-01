<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class FormatVideoDesc extends AbstractExtension
    {
        public function getFunctions()
        {
            return [
                new TwigFunction('FormatVideoDesc', [$this,'FormatVideoDesc']),
            ];
        }

        public function FormatVideoDesc($text)
        {
            $text = htmlspecialchars($text);
            $text = str_replace("\n", '<br>', $text);
            $text = preg_replace("/(http(s)?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*))/i", '<a href="$1">$1</a>', $text);
            return $text;
        }
    }
?>