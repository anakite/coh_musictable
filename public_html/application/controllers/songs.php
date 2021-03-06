<?php

class Songs extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->add_package_path(APPPATH.'third_party/datamapper');
        $this->load->library('datamapper');
        $this->load->library('MiscUtil');
        $this->load->library('HtmlUtil');
        $this->load->database();
        $this->load->model('Song');
        $this->load->model('Attachment');
        $this->load->model('Tag');
	$this->load->model('TagType');
        $this->load->model('User');
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->helper('html');
        $this->load->library('session');
        $this->load->library('user_agent');
    }

    public function login() {
        $referrer = site_url();
        if ($this->agent->is_referral())
        {
            if($this->session->flashdata('referrer')) {
                $referrer = $this->session->flashdata('referrer');
                $this->session->keep_flashdata('referrer');
            } else {
                $referrer = $this->agent->referrer();
                $this->session->set_flashdata('referrer', $referrer);
            }
        }
        $data = array();
        $data['message'] = '';
        $data['ask_password'] = TRUE;
        $data['referrer'] = $referrer;
        $data['admin'] = $this->session->userdata('admin') == 'TRUE';
        $userdata = $this->session->all_userdata();
        if($_POST && ($this->User->validate($_POST['username'], MD5($_POST['password'])) || (in_array('admin', $userdata) && $userdata['admin'] == 'TRUE'))) {
            $userdata['admin'] = 'TRUE';
            $this->session->set_userdata($userdata);
            redirect($this->session->flashdata('referrer'));
        } else {
            $data['message'] = "Not Logged In";
            $data['header'] = $this->load->view('templates/header_view',$data,true);
            $data['footer'] = $this->load->view('templates/footer_view',$data,true);
            $this->load->view('login_view',$data);
        }
    }

    public function logout() {
        //do something
        $userdata = $this->session->all_userdata();
        $userdata = array('admin' => 'FALSE');
        $this->session->set_userdata($userdata);
        $data = array();
        $data['message'] = 'Logged Out!';
        $data['ask_password'] = FALSE;
        $data['referrer'] = ($this->agent->is_referral()) ? $this->agent->referrer() : site_url();
        $data['admin'] = $this->session->userdata('admin') == 'TRUE';
        $data['header'] = $this->load->view('templates/header_view',$data,true);
        $data['footer'] = $this->load->view('templates/footer_view',$data,true);
        $this->load->view('login_view',$data);
    }

    public function index($pageno = -1) {

      if($pageno > 0)
	$page_index = $pageno;
      else
        $page_index = MiscUtil::getRequestItemInt('pageno', 1);
        
        $search_string = MiscUtil::getRequestItem('search_string', '');
        $data['search_string'] = $search_string;

	$data['alltagtypes'] = $this->TagType->get_all();

	$selected_tags = array();
	$selected_tags_flat = array();
	foreach ($data['alltagtypes'] as $tagtypeid => $tagtypename) {
	  $selected_tags[$tagtypename] = MiscUtil::getRequestItem('tagtype_'.$tagtypename, array());
          $selected_tags_flat = array_merge($selected_tags_flat, MiscUtil::getRequestItem('tagtype_'.$tagtypename, array()));
	}
	$data['selected_tags'] = $selected_tags;
	$data['selected_tags_flat'] = $selected_tags_flat;

	$temp_array = array();
	foreach ($data['alltagtypes'] as $tagtypeid => $tagtypename) {
	  $temp_array[$tagtypename] = $this->Tag->get_tags_for_tagtype($tagtypeid);
	}
	$data['allltags'] = $temp_array;
        
	$data['alltags'] =  $this->Tag->get_all();

	$query = $this->Song->get_multi(array('search_string' => $search_string, 'page_index' => $page_index-1, 'selected_tags' => $selected_tags));
	$data['songs'] = array_slice($query->result(), ($page_index-1) * Song::page_size, Song::page_size);
	$total_songs = $query->num_rows();
        $data['url'] = current_url();

        $data['page_index'] = $page_index;
        $data['total_pages'] = ceil($total_songs / Song::page_size);

        $data['admin'] = $this->session->userdata('admin') == 'TRUE';
        $data['header'] = $this->load->view('templates/header_view', $data, true);
        $data['table'] = $this->load->view('songs/table_view', $data, true);
        $data['footer'] = $this->load->view('templates/footer_view', $data, true);
        $this->load->view('songs/index_view', $data);
    }

    public function detail($id){
        try
        {
            $data['id'] = $id;
            $data['admin'] = $this->session->userdata('admin') == 'TRUE';
            $data['song'] = $this->Song->get_one($id);
            $data['attachments'] = $this->Attachment->get_attachments_for_song($id);
            $data['tags'] = $this->Tag->get_tags_for_song(array('id' => intval($id),'deep'=>'true'));
            $data['alltagtypes'] = $this->TagType->get_all();
            $temp_array = array();
            foreach ($data['alltagtypes'] as $tagtypeid => $tagtypename) {
                $temp_array[$tagtypename] = $this->Tag->get_tags_for_tagtype($tagtypeid);
            }
            $data['allltags'] = $temp_array;
            $data['quarantined_id'] = $this->Tag->get_by_name('Quarantined');
	    $data['quarantined'] = $this->Song->has_tag($id, $data['quarantined_id']);
            $data['header'] = $this->load->view('templates/header_view',$data,true);
            $data['footer'] = $this->load->view('templates/footer_view',$data,true);
            $this->load->view('songs/detail_view',$data);
        }
        catch(Exception $e)
        {
            show_error('Invalid Song ID!');
        }
    }

    public function edit($id){
        if($this->session->userdata('admin') != 'TRUE') {
            redirect('songs/login');
        }
        try
        {
            if($_POST) {
                $id = $this->update($id);
                redirect('songs/edit/' . $id . '?updated=true');
            }
            $data['admin'] = $this->session->userdata('admin') == 'TRUE';
            $data['id'] = $id;
            if ($id == 0) {
                $data['song'] = $this->Song->get_one($id);
                $data['song']->Title = 'Untitled';
		$data['showUpdated'] = false;
            } else {
                $data['song'] = $this->Song->get_one($id);
                $data['showUpdated'] = $this->input->get('updated');
                $data['attachments'] = $this->Attachment->get_attachments_for_song($id);
                $data['attachment_views'] = array();
                foreach($data['attachments'] as $a) {
                    $localdata = array();
                    $localdata['attachment'] = $a;
                    $localdata['new'] = FALSE;
                    array_push($data['attachment_views'], $this->load->view('songs/attachment_edit_view',$localdata,true));
                }
                $data['tags'] = $this->Tag->get_tags_for_song(array('id' => intval($id),'deep'=>'true'));
            }
	    $data['quarantined_id'] = $this->Tag->get_by_name('Quarantined');
	    $data['quarantined'] = $this->Song->has_tag($id, $data['quarantined_id']);
            $data['alltags'] =  $this->Tag->get_all(array('id' => intval($id),'deep'=>'true'));
	    $data['alltagtypes'] = $this->TagType->get_all();
            $temp_array = array();
            foreach ($data['alltagtypes'] as $tagtypeid => $tagtypename) {
		$temp_array[$tagtypename] = $this->Tag->get_tags_for_tagtype($tagtypeid);
            }
            $data['allltags'] = $temp_array;
            $data['header'] = $this->load->view('templates/header_view',$data,true);
            $data['footer'] = $this->load->view('templates/footer_view',$data,true);
            $this->load->view('songs/edit_view',$data);
        }
        catch(Exception $e)
        {
            show_error('Invalid Song ID!');
        }
    }
    
    private function updateWrapped($id) {
        $data = array();
        $data['Title'] = $_POST['title'];
        $data['Artist'] = $_POST['artist'];
        $data['Scripture'] = $_POST['scripture'];
        $data['LyricsExcerpt'] = $_POST['lyricsexcerpt'];
        $data['Notes'] = $_POST['notes'];
        $data['Quarantined'] = in_array('quarantined',$_POST);
        
        if($id == 0) {
            $id = $this->Song->add($data);
        } else {
            $this->Song->update($id, $data);
        }
        //update attachments
        //echo var_dump($_POST);
        $type_array = array();
        $url_array = array();
        $del_array = array();
        $new_array = array();
        foreach($_POST as $pk => $pv) {
            $field = '';
            $aid = '';
            sscanf($pk, "attachment_%[^_]_%d", $field, $aid);
            if($aid) {
                if($field == "type")
                    $type_array[$aid] = $pv;
                if($field == "url")
                    $url_array[$aid] = $pv;
                if($field == "delete")
                    $del_array[$aid] = $pv;
            }
        }
        foreach($new_array as $aid => $val) {
            $this->Attachment->add($aid, $val, $url_array[$aid]);
        }		
        foreach($type_array as $aid => $val) {
            $this->Attachment->update($aid, $val, $url_array[$aid]);
        }
        foreach($del_array as $aid => $val) {
            $this->Attachment->remove($aid);
        }
        return $id;
    }

    public function update($id) {
        if($this->session->userdata('admin') != 'TRUE') redirect('songs/login');
        if (!$_POST) return;
        
        try {
            $id = $this->updateWrapped($id);
        } catch(Exception $e) {
            show_error('Invalid Song ID!');
        }
        return $id;
    }
    
}

?>
