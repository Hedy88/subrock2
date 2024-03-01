<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class GetVideoViews extends AbstractExtension
    {
        public function __construct($___video_helper)
        {
            $this->____video_helper = $___video_helper;
        }

        public function getFunctions()
        {
            return [
                new TwigFunction('GetVideoViews', [$this,'GetVideoViews']),
            ];
        }

        public function GetVideoViews($vid)
        {
            return $this->____video_helper->get_video_views($vid);
        }
    }
?>