<?php
namespace ChenTube;

class video_helper {
    public function __construct($db)
    {
        $this->db = $db;
    }
    function check_view($vid, $ip)
    {
        $stmt = $this->db->query("SELECT id FROM views WHERE viewer = :viewer AND vid = :vid", [
            ':viewer' => $ip,
            ':vid' => $vid
        ]);
        if($stmt->rowCount() === 0) {
            $this->add_view($vid, $ip);
        }
    }

    function add_view($vid, $ip)
    {
        $this->db->query("INSERT INTO views (viewer, vid) VALUES (:viewer, :vid)", [
            ':viewer' => $ip,
            ':vid' => $vid
        ]);
    }

    function get_video_views($vid)
    {
        $stmt = $this->db->query("SELECT id FROM views WHERE vid = :vid", [
            ':vid' => $vid
        ]);
        
        return $stmt->rowCount();
    }
}
?>