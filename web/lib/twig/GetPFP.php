<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class GetPFP extends AbstractExtension
    {
        public function __construct($db)
        {
            $this->db = $db;
        }

        public function getFunctions()
        {
            return [
                new TwigFunction('GetPFP', [$this,'GetPFP']),
            ];
        }

        public function GetPFP($uuid)
        {
            global $_user_helper;
            return $_user_helper->GetPFP($uuid);
        }
    }
?>