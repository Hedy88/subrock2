<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class GetHostURL extends AbstractExtension
    {
        public function getFunctions()
        {
            return [
                new TwigFunction('GetHostURL', [$this,'GetHostURL']),
            ];
        }

        public function GetHostURL()
        {
            return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        }
    }
?>