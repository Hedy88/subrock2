<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class time_elapsed_string extends AbstractExtension
    {
        public function getFunctions()
        {
            return [
                new TwigFunction('time_elapsed_string', [$this,'time_elapsed_string']),
            ];
        }

        public function time_elapsed_string($time)
        {
            return \ChenTube\time_elapsed_string($time);
        }
    }
?>