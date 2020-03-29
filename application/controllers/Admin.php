<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin extends CI_Controller
{
   public function __construct()
   {
      parent::__construct();
      is_logged_in(); // nama bebas methodnya ada di helper
   }

   public function index()
   {
      $data['title'] = 'Dashboard';
      // mengambil semua data dr tabel database berdasarkan email yang ada di session
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

      $this->load->view('templates/header', $data);
      $this->load->view('templates/sidebar', $data);
      $this->load->view('templates/topbar', $data);
      $this->load->view('admin/index', $data);
      $this->load->view('templates/footer');
   }

   public function role()
   {
      $data['title'] = 'Role';
      // mengambil semua data dr tabel database berdasarkan email yang ada di session
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();

      $data['role'] = $this->db->get('user_role')->result_array();

      $this->load->view('templates/header', $data);
      $this->load->view('templates/sidebar', $data);
      $this->load->view('templates/topbar', $data);
      $this->load->view('admin/role', $data);
      $this->load->view('templates/footer');
   }

   public function roleAccess($role_id)
   {
      $data['title'] = 'Role Access';
      // mengambil data user dr tabel user berdasarkan email yang ada di session
      $data['user'] = $this->db->get_where('user', ['email' => $this->session->userdata('email')])->row_array();
      // mengambil data role dr tabel user_role berdasarkan id yang di dapat dari role_id
      $data['role'] = $this->db->get_where('user_role', ['id' => $role_id])->row_array();

      $this->db->where('id !=', 1); // menampilkan data menu dr tabel user_menu kecuali id 1
      $data['menu'] = $this->db->get('user_menu')->result_array();

      $this->load->view('templates/header', $data);
      $this->load->view('templates/sidebar', $data);
      $this->load->view('templates/topbar', $data);
      $this->load->view('admin/role-access', $data);
      $this->load->view('templates/footer');
   }

   public function changeAccess()
   {
      // ambil data dari ajax yang ada di footer
      $menu_id = $this->input->post('menuId');
      $role_id = $this->input->post('roleId');

      $data = [
         'role_id' => $role_id,
         'menu_id' => $menu_id
      ];

      $result = $this->db->get_where('user_access_menu', $data);
      if ($result->num_rows() < 1) {
         $this->db->insert('user_access_menu', $data); // kalau tidak ada maka insert
      } else {
         $this->db->delete('user_access_menu', $data); // kalau ada maka hapus
      }
      $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Access Changed!</div>');
   }
}
