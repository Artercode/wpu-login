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

         // cek jika ada gambar yang akan diupload
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

         $this->db->set('name', $name);
         $this->db->where('email', $email);
         $this->db->update('user');
         $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Your profile has been updated. </div>');
         redirect('user');
      }
   }
}
