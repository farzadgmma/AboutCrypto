<?php
// src/includes/database.php
// این کلاس مسئولیت مدیریت کامل اتصال به دیتابیس را بر عهده دارد.

class Database {
    // مشخصات اتصال به دیتابیس که از فایل config.php خوانده می‌شوند.
    private $host = DB_HOST;         // میزبان دیتابیس
    private $db_name = DB_NAME;      // نام دیتابیس
    private $username = DB_USER;     // نام کاربری دیتابیس
    private $password = DB_PASS;     // رمز عبور دیتابیس
    public $conn;                    // متغیری برای نگهداری آبجکت اتصال

    /**
     * متد اصلی برای برقراری اتصال به دیتابیس با استفاده از PDO.
     * PDO یک روش مدرن و امن برای کار با دیتابیس در PHP است.
     * @return PDO|null آبجکت اتصال PDO در صورت موفقیت، و null در صورت شکست.
     */
    public function getConnection() {
        // ابتدا متغیر اتصال را null قرار می‌دهیم تا از اتصالات قبلی جلوگیری شود.
        $this->conn = null;

        try {
            // ساخت رشته DSN (Data Source Name) برای اتصال
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            // ایجاد یک نمونه جدید از کلاس PDO برای برقراری اتصال
            // این بخش مهم‌ترین قسمت برای اتصال به دیتابیس است.
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // تنظیم حالت خطا در PDO. این دستور باعث می‌شود در صورت بروز خطا، یک استثنا (Exception) پرتاب شود.
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // تنظیم انکودینگ ارتباط با دیتابیس به utf8mb4 تا از کاراکترهای فارسی و ایموجی‌ها پشتیبانی شود.
            $this->conn->exec("set names utf8mb4");

        } catch(PDOException $exception) {
            // اگر در هر یک از مراحل بالا خطایی رخ دهد (مثلاً اطلاعات اتصال اشتباه باشد)،
            // این بلوک کد اجرا می‌شود.

            // ثبت پیغام خطا در لاگ سرور به جای نمایش آن به کاربر.
            // این کار امنیت را افزایش می‌دهد.
            error_log("خطا در اتصال به دیتابیس: " . $exception->getMessage());

            // در صورت بروز خطا، null را باز می‌گردانیم تا کدی که این متد را فراخوانی کرده، متوجه شکست اتصال شود.
            return null;
        }

        // اگر همه چیز موفقیت‌آمیز باشد، آبجکت اتصال را باز می‌گردانیم.
        return $this->conn;
    }
}
?>