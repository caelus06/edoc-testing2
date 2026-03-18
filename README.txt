===========================================================
E-Doc: Document Requesting System
===========================================================

E-Doc is a web-based application designed to simplify the process 
of requesting academic documents. Users can submit document requests 
online, upload required files, and track the status of their requests.

-----------------------------------------------------------
Technologies Used
-----------------------------------------------------------
- PHP
- MySQL
- HTML
- CSS
- JavaScript
- Tesseract OCR (UB Mannheim)

-----------------------------------------------------------
System Users
-----------------------------------------------------------
- User
- Registrar
- MIS

-----------------------------------------------------------
System Requirements
-----------------------------------------------------------
- XAMPP
- Web Browser

-----------------------------------------------------------
Installation / Setup Instructions (Localhost)
-----------------------------------------------------------
1. Install XAMPP on your computer.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Copy the project folder named "edoc" into the XAMPP htdocs directory.

   Example:
   C:\xampp\htdocs\edoc

4. Open your browser and go to:
   http://localhost/phpmyadmin

5. Create a new database named:
   edoc_system

6. Import the provided edoc_system.sql file into the database.

7. After importing, open the system in your browser:
   http://localhost/edoc

-----------------------------------------------------------
GitHub Installation (Clone the Project)
-----------------------------------------------------------
1. Open Command Prompt.
2. Navigate to your XAMPP htdocs folder.

   Example:
   cd C:\xampp\htdocs

3. Clone the repository using:
   git clone https://github.com/russelp642/edoc.git

4. Start Apache and MySQL in XAMPP.
5. Create the database edoc_system in phpMyAdmin.
6. Import the provided SQL file.
7. Open the system in your browser:

   http://localhost/edoc

The E-Doc system should now be accessible.

-----------------------------------------------------------
Login Credentials
-----------------------------------------------------------
Registrar:
- Email: 1@gmail.com | Password: 12345678 | Status: VERIFIED
- Email: 2@gmail.com | Password: 12345678 | Status: PENDING
- Email: 3@gmail.com | Password: 12345678 | Status: VERIFIED
- Email: 4@gmail.com | Password: 12345678 | Status: VERIFIED
- Email: 5@gmail.com | Password: 12345678 | Status: VERIFIED
- Email: 6@gmail.com | Password: 12345678 | Status: VERIFIED

MIS:
- Email: mis@gmail.com | Password: 12345678 | Status: VERIFIED

Users:
- Email: carlo.bautista@gmail.com | Password: carlo123 | Status: PENDING
- Email: maria.reyes@gmail.com | Password: maria123 | Status: PENDING
- Email: john.santos@gmail.com | Password: john1234 | Status: REJECTED
- Email: mary.garcia@gmail.com | Password: mary1234 | Status: VERIFIED
- Email: mark.reyes@gmail.com | Password: mark1234 | Status: VERIFIED
- Email: anna.cruz@gmail.com | Password: anna1234 | Status: VERIFIED
- Email: paul.mendoza@gmail.com | Password: paul1234 | Status: VERIFIED
- Email: jane.diaz@gmail.com | Password: jane1234 | Status: VERIFIED
- Email: kevin.torres@gmail.com | Password: kevin1234 | Status: VERIFIED
- Email: grace.rivera@gmail.com | Password: grace1234 | Status: VERIFIED
- Email: jejomar.lim@gmail.com | Password: jejomar123 | Status: VERIFIED
- Email: diwata.dalisay@gmail.com | Password: diwata123 | Status: VERIFIED

-----------------------------------------------------------
Tesseract OCR Installation (Windows)
-----------------------------------------------------------
Tesseract OCR is required for text recognition in uploaded documents. 
UB Mannheim provides a Windows installer.

Steps:
1. Download the latest installer:
   - 64-bit: tesseract-ocr-w64-setup-5.5.0.20241111.exe
   - Older versions (32-bit and 64-bit) are also available.

2. Run the installer.

3. IMPORTANT WARNING:
   - Install Tesseract in the suggested directory or a new directory.
   - The uninstaller removes the entire installation directory.
   - If installed in an existing directory, that directory (including 
     all subfolders and files) will be deleted during uninstall.

4. Verify installation by running in Command Prompt:
   tesseract --version

-----------------------------------------------------------
Documentation & Models
-----------------------------------------------------------
- Tesseract Wiki (UB Mannheim): https://github.com/UB-Mannheim/tesseract/wiki
- Doxygen Documentation: https://digi.bib.uni-mannheim.de/tesseract/doc/

Models for Historic Prints:
- Standard: deu_latf, script/Fraktur
- UB Mannheim trained models: Fraktur_5000000, frak2021, german_print

End of README
===========================================================