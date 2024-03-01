<?php
namespace ChenTube\Twig;

use Twig\Extension\AbstractExtension;
    use Twig\TwigFunction;

    class GetUsername extends AbstractExtension
    {
        public function getFunctions()
        {
            return [
                new TwigFunction('GetUsername', [$this,'GetUsername']),
            ];
        }

        public function GetUsername($uuid, $for = 'username', $comment = '')
        {
            global $_user_helper;
            if($for == 'username') {
                return $_user_helper->GetUsername($uuid);
            }
            if($for == 'comment') {
                return $_user_helper->GetCommentUsername($uuid, $comment);
            }
        }
    }

?>