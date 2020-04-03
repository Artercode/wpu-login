<?php

defined('BASEPATH') or exit('No direct script access allowed');

class User extends CI_Controller
{
   public function __construct()
   {
      parent::__construct();
      is_logged_in(); // nama bebas methodnya ada di helper
   }

   public function index()
   {
      $data['title'] = 'My Profile';
      // mengambil semua data dr tabel database berdasarkan email yang ada di session
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

      $this->load->view('templates/header', $data);
      $this->load->view('templates/sidebar', $data);
      $this->load->view('templates/topbar', $data);
      $this->load->view('user/index', $data);
      $this->load->view('templates/footer');
   }

   public function edit()
   {
      // title harus sam dengan title yang ada di tabel user_submenu
      $data['title'] = 'Edit Profile';
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

      $this->form_validation->set_rules('name', 'Full Name', 'trim|required');

      if ($this->form_validation->run() == false) {
         $this->load->view('templates/header', $data);
         $this->load->view('templates/sidebar', $data);
         $this->load->view('templates/topbar', $data);
         $this->load->view('user/edit', $data);
         $this->load->view('templates/footer');
      } else {
         $name = $this->input->post('name');
         $email = $this->input->post('email');

         // edit gambar - cek jika ada gambar yang akan diupload
         $upload_image = $_FILES['image']['name'];
         if ($upload_image) {
            $config['allowed_types'] = 'gif|jpg|png';
            $config['max_size']      = '2048';
            $config['upload_path'] = './assets/img/profile/';
            $this->load->library('upload', $config);

            if ($this->upload->do_upload('image')) {
               // gambar lama ambil dari tabel user kolom image
               $old_image = $data['user']['image'];
               // jika gambar lama bukan defaolt.jpg maka hapus
               if ($old_image != 'default.jpg') {
                  unlink(FCPATH . 'assets/img/profile/' . $old_image);
               }
               $new_image = $this->upload->data('file_name');
               $this->db->set('image', $new_image);
            } else {
               echo $this->upload->display_errors();
            }
         }
         // edit nama
         $this->db->set('name', $name);
         $this->db->where('email', $email);
         $this->db->update('user');
         $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Your profile has been updated. </div>');
         redirect('user');
      }
   }

   public function changePassword()
   {
      $data['title'] = 'Change Password';
      // mengambil semua data dr tabel database berdasarkan email yang ada di session
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

      $this->form_validation->set_rules('current_password', 'Current Password', 'trim|required');
      $this->form_validation->set_rules('new_password1', 'New Password', 'trim|required|min_length[4]');
      $this->form_validation->set_rules('new_password2', 'New Password', 'trim|required|matches[new_password1]');


      if ($this->form_validation->run() == false) {
         $this->load->view('templates/header', $data);
         $this->load->view('templates/sidebar', $data);
         $this->load->view('templates/topbar', $data);
         $this->load->view('user/changepassword', $data);
         $this->load->view('templates/footer');
      } else {
         $current_password = $this->input->post('current_password');
         $new_password = $this->input->post('new_password1');

         if (!password_verify($current_password, $data['user']['password'])) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Wrong Current Password. </div>');
            redirect('user/changepassword');
         } else {
            if ($current_password == $new_password) {
               $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">New Password cannot be the same as Current Password. </div>');
               redirect('user/changepassword');
            } else {
               $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

               $this->db->set('password', $password_hash);
               $this->db->where('email', $this->session->userdata('email'));
               $this->db->update('user');
               $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Password changed. </div>');
               redirect('user/changepassword');
            }
         }
      }
   }
}
