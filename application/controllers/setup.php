<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Setup extends CI_Controller
{
    private $db_error;
    private $prefix;

    function __construct()
    {
        parent::__construct();

        # load helper
        $this->load->helper(array('url', 'form', 'text', 'elearning', 'security', 'file', 'number', 'date'));

        # load library
        $this->load->library(array('form_validation', 'twig', 'user_agent'));

        # delimiters form validation
        $this->form_validation->set_error_delimiters('<span class="text-error"><i class="icon-info-sign"></i> ', '</span>');

        # cek pengaturan database
        include APPPATH . 'config/database.php';

        $link = @mysqli_connect($db['default']['hostname'], $db['default']['username'], $db['default']['password']);
        if (!$link) {
            $this->db_error = get_alert('error', 'Failed to connect to the server: ' . mysqli_connect_error());
        }
        elseif (!@mysqli_select_db($link, $db['default']['database'])) {
            $this->db_error = get_alert('error', 'Failed to connect to the database: ' . mysqli_error($link));
        }

        if (empty($this->db_error)) {
            $this->load->database();
            $this->prefix = $db['default']['dbprefix'];

            # load model
            $this->load->model(array('config_model', 'kelas_model', 'login_model', 'mapel_model', 'materi_model', 'pengajar_model', 'siswa_model', 'tugas_model'));
        }
    }

    function index($step = '')
    {
        switch ($step) {
            case '4':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (empty($check)) {
                    redirect('setup/index/2');
                }

                # cek kelas
                $check = $this->db->count_all_results('kelas');
                if (empty($check)) {
                    redirect('setup/index/3');
                }

                $this->twig->display('install-step-4.html');
            break;

            case '3':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (empty($check)) {
                    redirect('setup/index/2');
                }

                if (!empty($_POST)) {
                    # simpan kelas
                    foreach ($_POST['kelas'] as $key => $val) {
                        # cek parent sudah ada belum
                        $this->db->where('nama', "KELAS $key");
                        $this->db->where('parent_id', null);
                        $result = $this->db->get('kelas');
                        $parent = $result->row_array();
                        if (empty($parent)) {
                            $parent_id = $this->kelas_model->create("KELAS $key", null);
                        } else {
                            $parent_id = $parent['id'];
                        }

                        # simpan child
                        foreach ($val as $child) {
                            $this->db->where('nama', "KELAS $key - $child");
                            $this->db->where('parent_id', $parent_id);
                            $result = $this->db->get('kelas');
                            $result = $result->row_array();
                            if (empty($result)) {
                                $this->kelas_model->create("KELAS $key - $child", $parent_id);
                            }
                        }
                    }

                    # simpan mapel
                    foreach ($_POST['mapel'] as $nama) {
                        # cek mapel
                        $this->db->where('nama', $nama);
                        $result = $this->db->get('mapel');
                        if (empty($result)) {
                            $this->mapel_model->create($nama);
                        }
                    }

                    redirect('setup/index/4');
                }

                $data['jenjang'] = get_pengaturan('jenjang', 'value');

                $this->twig->display('install-step-3.html', $data);
            break;

            case '2':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (!empty($check)) {
                    redirect('setup/index/3');
                }

                if ($this->form_validation->run('setup/index/2') == true) {
                    foreach ($_POST as $key => $val) {
                        $this->config_model->create(
                            $key,
                            $key,
                            $val
                        );
                    }

                    redirect('setup/index/3');
                }

                $this->twig->display('install-step-2.html');
            break;

            case '1':
            default:
                if (empty($this->db_error)) {
                    # cek tabel pengaturan, jika sudah ada lanjut ke step 2
                    $error = false;
                    try {
                        $this->db->get('pengaturan');
                    } catch (Exception $e) {
                        $error = true;
                    }

                    if (!$error) {
                        redirect('setup/index/2');
                    }

                    # run query
                    $sql = file_get_contents(APPPATH . 'install/table-master');
                    $sql = str_replace('{$prefix}', $this->prefix, $sql);

                    $sqls = explode(';', $sql);
                    array_pop($sqls);

                    $this->db->trans_start();
                    foreach($sqls as $statement){
                        $statment = $statement . ";";
                        $this->db->query($statement);
                    }
                    $this->db->trans_complete();

                    redirect('setup/index/2');
                }

                $data['error'] = $this->db_error;
                $this->twig->display('install-step-1.html', $data);
            break;
        }
    }
}
