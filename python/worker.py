import os
import json
import imaplib
import email
from email.header import decode_header
import mysql.connector
from datetime import datetime
import time
def connect_to_db():
    """Connect to MySQL database."""
    return mysql.connector.connect(
        host="localhost",  # Sesuaikan dengan host MySQL Anda
        user="root",       # Sesuaikan dengan user MySQL Anda
        password="skyhwk12",       # Sesuaikan dengan password MySQL Anda
        database="intilab_produksi" # Sesuaikan dengan nama database Anda
    )

def parse_email_header(value):
    """Decode email header to readable string."""
    if value:
        decoded = decode_header(value)
        return ''.join(
            part.decode(encoding or 'utf-8') if isinstance(part, bytes) else part
            for part, encoding in decoded
        )
    return ""

def fetch_emails(imap, folder):
    """Fetch email metadata from the specified folder without marking emails as read."""
    imap.select(folder)
    status, messages = imap.search(None, "ALL")
    email_list = []

    print(status)

    if status != "OK":
        return email_list

    for num in messages[0].split():
        # Gunakan BODY.PEEK[HEADER] agar email tidak ditandai sebagai dibuka
        status, data = imap.fetch(num, "(BODY.PEEK[HEADER] FLAGS UID)")
        if status != "OK":
            continue

        # Ambil header email tanpa mengubah status
        header_data = data[0][1]
        msg = email.message_from_bytes(header_data)
        subject = parse_email_header(msg["Subject"])
        sender = parse_email_header(msg["From"])
        recipient = parse_email_header(msg["To"])
        date = msg["Date"]
        message_id = msg["Message-ID"]

        # Ambil UID dan flags
        uid_data = imap.fetch(num, "UID")[1][0].decode()
        uid = int(uid_data.split("UID")[-1].strip(" )"))

        flags = data[0][0].decode().split()

        # Tambahkan metadata ke list
        email_list.append({
            "subject": subject,
            "from": sender,
            "to": recipient,
            "date": date,
            "message_id": message_id,
            "size": len(header_data),
            "uid": uid,
            "msgno": int(num),
            "recent": int("\\Recent" in flags),
            "flagged": int("\\Flagged" in flags),
            "answered": int("\\Answered" in flags),
            "deleted": int("\\Deleted" in flags),
            "seen": int("\\Seen" in flags),
            "draft": int("\\Draft" in flags),
            "update": int(datetime.now().timestamp())  # Waktu pengambilan data
        })

    return email_list

def save_to_txt(email_data, user_email, folder, config_file):
    """
    Simpan email data ke file teks dalam format JSON.stringify yang valid.
    
    Parameters:
    - email_data: Data email yang akan disimpan.
    - folder: Nama folder email (misal: INBOX, Sent).
    - config_file: Nama file konfigurasi (akan digunakan untuk penamaan file output).
    """
    # Ubah nama folder menjadi huruf kecil untuk penyesuaian
    folder = folder.lower()
    # Nama file output berdasarkan nama file konfigurasi
    config_filename = os.path.splitext(os.path.basename(config_file))[0]
    # Path direktori output
    output_dir = f"/var/www/html/v3/storage/repository/{folder}"
    # Pastikan direktori tujuan ada
    os.makedirs(output_dir, exist_ok=True)
    # Path file tujuan
    output_file = os.path.join(output_dir, f"{config_filename}.txt")
    
    # Simpan data ke file dalam format JSON valid (array JSON)
    with open(output_file, "w") as file:
        json.dump(email_data, file)

def process_user(config_file):
    """Process emails for a single user."""
    with open(config_file, "r") as file:
        config = json.load(file)

    imap_host = config["incoming"]["hostname"]
    imap_port = int(config["incoming"]["port"])
    user_email = config["email"]
    password = config["password"]

    folders = ["INBOX", "SENT", "SPAM", "TRASH"]

    try:
        imap = imaplib.IMAP4(imap_host, imap_port)
        imap.starttls()
        imap.login(user_email, password)

        db_connection = connect_to_db()
        cursor = db_connection.cursor()

        for folder in folders:
            emails = fetch_emails(imap, folder)
            if emails:
                save_to_txt(emails, user_email, folder, config_file)

        db_connection.commit()
        cursor.close()
        db_connection.close()

        imap.logout()

    except Exception as e:
        print(f"Error processing user {user_email}: {e}")

if __name__ == "__main__":
    settings_path = "/var/www/html/v3/storage/repository/setting_mail/"
    
    while True:
        print("Memulai pemrosesan semua file konfigurasi email...")
        for config_file in os.listdir(settings_path):
            if config_file.endswith(".txt"):
                try:
                    print(f"Memproses file: {config_file}")
                    process_user(os.path.join(settings_path, config_file))
                except Exception as e:
                    print(f"Error saat memproses {config_file}: {str(e)}")
        
        print("Semua file telah diproses. Memulai kembali dari awal...")
        time.sleep(60)  # Tunggu 1 menit sebelum memulai lagi

