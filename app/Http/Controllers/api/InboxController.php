<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Repository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpImap\Mailbox;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = Repository::dir('inbox')->key($this->karyawan)->get();
            return response()->json(['data' => json_decode($data, true) ?? [], 'message' => 'Data berhasil diambil'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fetchFromServer(Request $request)
    {
        try {
            $data = Repository::dir('setting_mail')->key($this->karyawan)->get();
            if(empty($data)) {
                return response()->json(['message' => 'Setting email belum dikonfigurasi'], 404);
            }

            $data = json_decode($data, true);

            $imapConnection = imap_open(
                '{'.$data['incoming']['hostname'].':'.$data['incoming']['port'].'/'.$data['protocol'].'/'.$data['incoming']['connection_security'].'}INBOX',
                $data['email'],
                $data['password']
            );
    
            if (!$imapConnection) {
                return response()->json(['error' => imap_last_error()], 401);
            }
    
            // Ambil daftar email
            $emails = imap_search($imapConnection, 'ALL');
            if (!$emails) {
                return response()->json(['message' => []], 404);
            }
    
            $output = [];
            foreach (array_reverse($emails) as $emailNumber) {
                $overview = imap_fetch_overview($imapConnection, $emailNumber, 0);
                dd($overview);
                $output[] = [
                    'subject' => $overview[0]->subject,
                    'from' => $overview[0]->from,
                    'date' => $overview[0]->date,
                    'size' => $overview[0]->size,
                    'status' => $overview[0]->seen == 1 ? 'read' : 'unread',
                    'uid' => $overview[0]->uid,
                    'message_id' => $overview[0]->message_id
                ];
            }
    
            imap_close($imapConnection);

            if(empty($output)) {
                return response()->json(['message' => []], 404);
            }

            Repository::dir('inbox')->key($this->karyawan)->save(json_encode($output));

            return response()->json(['data' => $output, 'message' => 'Email berhasil diperbarui'], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function viewDetail(Request $request)
    {
        try {
            $emailID = $request->uid;
            
            $data = Repository::dir('setting_mail')->key($this->karyawan)->get();
            
            if(empty($data)) {
                return response()->json(['message' => 'Setting email belum dikonfigurasi'], 404);
            }

            $dataEmail = Repository::dir('inbox')->key($this->karyawan)->get();

            $dataEmail = json_decode($dataEmail, true);
            foreach ($dataEmail as &$email) {
                if ($email['uid'] == $emailID) {
                    $email['status'] = 'read';
                    break;
                }
            }

            Repository::dir('inbox')->key($this->karyawan)->save(json_encode($dataEmail));

            $data = json_decode($data, true);

            $imapPath = '{' . $data['incoming']['hostname'] . ':' . $data['incoming']['port'] . '/' . $data['protocol'] . '/' . $data['incoming']['connection_security'] . '}INBOX';
            
            $dirAttachments = public_path('email/'.$this->karyawan.'/attachments');

            if (!is_dir($dirAttachments)) {
                mkdir($dirAttachments, 0775, true);
            }
            
            $mailbox = new Mailbox(
                $imapPath,
                $data['email'],
                $data['password'],
                $dirAttachments,
                'UTF-8'
            );

            $mail = $mailbox->getMail($emailID);
            
            // Mengambil informasi lampiran
            $attachments = [];
            if (!empty($mail->getAttachments())) {
                foreach ($mail->getAttachments() as $attachment) {
                    $path = \explode("/", $attachment->filePath);
                    $attachments[] = [
                        'name' => $attachment->name,
                        'filePath' => env('APP_URL').'/public/email/'.$this->karyawan.'/attachments/'. end($path)
                    ];
                }
            }

            // Menambahkan informasi lampiran ke response
            $mailData = [
                'fromName' => $mail->fromName,
                'fromAddress' => $mail->fromAddress,
                'subject' => $mail->subject,
                'textHtml' => $mail->textHtml,
                'textPlain' => $mail->textPlain,
                'cc' => $mail->cc,
                'attachments' => $attachments
            ];

            return response()->json([
                'data' => $mailData,
                'message' => 'Email berhasil diambil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $emailID = $request->uid;
            $data = Repository::dir('setting_mail')->key($this->karyawan)->get();
            if(empty($data)) {
                return response()->json(['message' => 'Setting email belum dikonfigurasi'], 404);
            }

            $data = json_decode($data, true);

            $imapConnection = imap_open(
                '{'.$data['incoming']['hostname'].':'.$data['incoming']['port'].'/'.$data['protocol'].'/'.$data['incoming']['connection_security'].'}INBOX',
                $data['email'],
                $data['password']
            );

            if (!$imapConnection) {
                return response()->json(['error' => imap_last_error()], 401);
            }

            $deleteFlag = imap_delete($imapConnection, $emailID, FT_UID);
            if (!$deleteFlag) {
                throw new Exception('Failed to mark email for deletion: ' . imap_last_error());
            }

            $expunge = imap_expunge($imapConnection);
            if (!$expunge) {
                throw new Exception('Failed to expunge deleted emails: ' . imap_last_error());
            }

            $dataEmail = Repository::dir('inbox')->key($this->karyawan)->get();

            $dataEmail = json_decode($dataEmail, true);
            foreach ($dataEmail as $key => $email) {
                if ($email['uid'] == $emailID) {
                    unset($dataEmail[$key]);
                    break;
                }
            }

            Repository::dir('inbox')->key($this->karyawan)->save(json_encode(array_values($dataEmail)));

            imap_close($imapConnection);

            return response()->json(['message' => 'Email berhasil dihapus'], 200);
    
        } catch(\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }
}