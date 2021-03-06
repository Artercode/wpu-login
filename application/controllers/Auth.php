<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{
   public function __construct()
   {
      parent::__construct();
      // $this->load->library('form_validation');
   }

   public function index()
   {
      // supaya kalau sudah login gak bisa masuk ke auth dari url
      if ($this->session->userdata('email')) {
         redirect('user');
      }

      $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
      $this->form_validation->set_rules('password', 'Password', 'trim|required');

      if ($this->form_validation->run() == false) {
         $data['title'] = 'Login Page';
         $this->load->view('templates/auth_header', $data);
         $this->load->view('auth/login');
         $this->load->view('templates/auth_footer');
      } else {
         $this->_login();
      }
   }

   private function _login()
   {
      $email      = $this->input->post('email');
      $password   = $this->input->post('password');
      // mengambil data dr tabel user berdasarkan email yg didapat dr input email(login)
      $user = $this->db->get_where('user', ['email' => $email])->row_array();

      if ($user) {
         // jika user aktif
         if ($user['is_active'] == 1) {
            // cek password apakah sama dengan password yang ada di tabel user atau tidak
            if (password_verify($password, $user['password'])) {
               // setelah login siapkan data yg dibutuhkan saja, jgn memasukan password dlm session
               $data = [
                  'email'     => $user['email'],
                  'role_id'   => $user['role_id'],
               ];
               $this->session->set_userdata($data);

               if ($user['role_id'] == 1) {
                  redirect('admin');
               } else {
                  redirect('user');
               }
            } else {
               $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Wrong password!</div>');
               redirect('auth');
            }
         } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">This email has not been activated!</div>');
            redirect('auth');
         }
      } else {
         $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Email is not register!</div>');
         redirect('auth');
      }
   }


   public function registration()
   {
      // supaya kalau sudah login gak bisa masuk ke auth dari url
      if ($this->session->userdata('email')) {
         redirect('user');
      }

      $this->form_validation->set_rules('name', 'Name', 'required|trim');
      $this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email|is_unique[user.email]', [
         'is_unique' => 'This email has already registered!'
      ]);
      $this->form_validation->set_rules('password1', 'Password', 'required|trim|min_length[3]|matches[password2]', [
         // untuk merubah pesan bawaan dari CI
         'matches' => 'Password dont match',
         'min_length' => 'Password too short!'
      ]);
      $this->form_validation->set_rules('password2', 'Password', 'required|trim|matches[password1]');

      // jika validation ada kesalahan maka ..
      if ($this->form_validation->run() == false) {
         $data['title'] = 'WPU User Registration';
         $this->load->view('templates/auth_header', $data);
         $this->load->view('auth/registration');
         $this->load->view('templates/auth_footer');
      } else {
         $email = $this->input->post('email', true);
         // jika benar maka akan mengambil data sesuai urutan tabel
         $data = [
            // penambahan true untuk menghindari SSS
            'name'         => htmlspecialchars($this->input->post('name', true)),
            'email'        => htmlspecialchars($email),
            'image'        => 'default.jpg',
            'password'     => password_hash($this->input->post('password1'), PASSWORD_DEFAULT),
            'role_id'      => 2,
            'is_active'    => 0,
            'date_created' => time()
         ];
         // siapkan token untuk aktivasi
         $token = base64_encode(random_bytes(32));
         $user_token = [
            'email'        => $email,
            'token'        => $token,
            'date_created' => time()
         ];

         // mamasukan data ke database - data harus berurut sesuai di tabel
         $this->db->insert('user', $data);
         $this->db->insert('user_token', $user_token);
         // untuk mengirim email aktivasi
         $this->_sendEmail($token, 'verify'); // sendEmail untuk klarifikasi register

         $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Congratulation! your account has been created. Please Activate your account. </div>');
         redirect('auth');
      }
   }

   private function _sendEmail($token, $type)
   {
      $config = [
         'protocol'  => 'smtp',
         'smtp_host' => 'ssl://smtp.googlemail.com',
         'smtp_user' => 'emailactivationkc@gmail.com',
         'smtp_pass' => '',
         'smtp_port' => 465, // port milik google
         'mailtype'  => 'html',
         'charset'   => 'utf-8',
         'newline'   => "\r\n"  // harus ada supaya bisa mengirimkan email aktivasi
      ];
      $this->load->library('email', $config);
      // isi email
      $this->email->from('emailactivationkc@gmail.com', 'Web Programing UNPAS');
      $this->email->to($this->input->post('email'));

      if ($type == 'verify') {
         $this->email->subject('Account verification');
         $this->email->message('Click this link to verify your account : <a href="' . base_url() . 'auth/verify?email=' . $this->input->post('email') . '&token=' . urlencode($token) . '">Activate</a>');
      } else 
      if ($type == 'forgot') {
         $this->email->subject('Reset Password');
         $this->email->message('Click this link to reset your password: <a href="' . base_url() . 'auth/resetpassword?email=' . $this->input->post('email') . '&token=' . urlencode($token) . '">Reset Password</a>');
      }

      if ($this->email->send()) {
         return true;
      } else {
         echo $this->email->print_debugger();
         die;
      }
   }

   public function verify()
   {
      $email = $this->input->get('email');
      $token = $this->input->get('token');

      $user = $this->db->get_where('user', ['email' => $email])->row_array();
      if ($user) {
         $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();

         if ($user_token) {
            // date_created harus kurang dari 1 hari
            if (time() - $user_token['date_created'] < (60 * 60 * 24)) {
               // jika masih kurang dari 1 hari - ubah is_active jadi 1
               $this->db->set('is_active', 1);
               $this->db->where('email', $email);
               $this->db->update('user');
               // hapus tokennya sudah tidak terpakai
               $this->db->delete('user_token', ['email' => $email]);

               $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">' . $email . ' has been activated! Please Login</div>');
               redirect('auth');
            } else {
               // jika melebihi 1 hari - hapus user register dan user token
               $this->db->delete('user', ['email' => $email]);
               $this->db->delete('user_token', ['email' => $email]);

               $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Account activation failed! Token expiered</div>');
               redirect('auth');
            }
         } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Account activation failed! Wrong token</div>');
            redirect('auth');
         }
      } else {
         $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Account activation failed! Wrong email</div>');
         redirect('auth');
      }
   }


   public function logout()
   {
      $this->session->unset_userdata('email');
      $this->session->unset_userdata('role_id');

      $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">You have been logged out!</div>');
      redirect('auth');
   }

   public function blocked()
   {
      $this->load->view('auth/blocked');
   }

   public function forgotPassword()
   {
      $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');

      if ($this->form_validation->run() == false) {
         $data['title'] = 'Forgot Password';
         $this->load->view('templates/auth_header', $data);
         $this->load->view('auth/forgot_password');
         $this->load->view('templates/auth_footer');
      } else {
         $email = $this->input->post('email');
         // ambil sebaris data dari tabel user sesuai g email yg sama dadi email inputan dan is_active nya = 1
         $user = $this->db->get_where('user', ['email' => $email, 'is_active' => 1])->row_array();
         if ($user) {
            $token = base64_encode(random_bytes(32));
            $user_token = [
               'email'        => $email,
               'token'        => $token,
               'date_created' => time()
            ];
            $this->db->insert('user_token', $user_token);
            // jalankan sent email kirim data $token dan type forgot
            $this->_sendEmail($token, 'forgot');
            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Please check your email to reset your password!</div>');
            redirect('auth/forgotpassword');
         } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Email is not registered or activated!</div>');
            redirect('auth/forgotpassword');
         }
      }
   }

   public function resetpassword()
   {
      $email = $this->input->get('email');
      $token = $this->input->get('token');

      $user = $this->db->get_where('user', ['email' => $email])->row_array();

      if ($user) {
         $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();
         if ($user_token) {
            // memanfaatkan session untuk data reset saat email reset password di click
            $this->session->set_userdata('reset_email', $email);
            $this->changePassword(); // jalankan method changePassword
         } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Reset password failed! Wrong token</div>');
            redirect('auth');
         }
      } else {
         $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Reset password failed! Wrong email</div>');
         redirect('auth');
      }
   }

   public function changePassword()
   {
      if (!$this->session->userdata('reset_email')) {
         redirect('auth');
      }

      $this->form_validation->set_rules('password1', 'Password', 'required|trim|min_length[3]|matches[password2]', [
         // untuk merubah pesan bawaan dari CI
         'matches' => 'Password dont match',
         'min_length' => 'Password too short!'
      ]);
      $this->form_validation->set_rules('password2', 'Repeat Password', 'required|trim|matches[password1]');
      if ($this->form_validation->run() == false) {
         $data['title'] = 'Change Password';
         $this->load->view('templates/auth_header', $data);
         $this->load->view('auth/change_password');
         $this->load->view('templates/auth_footer');
      } else {
         $password = password_hash($this->input->post('password1'), PASSWORD_DEFAULT);
         $email = $this->session->userdata('reset_email');

         $this->db->set('password', $password); // ubah password dengan $password dari inputan
         $this->db->where('email', $email); // berdasarkan email sesuai $email dari session
         $this->db->update('user');

         $this->session->unset_userdata('reset_email');

         $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Password has been changed! please login</div>');
         redirect('auth');
      }
   }
}

/* End of file Auth.php */
