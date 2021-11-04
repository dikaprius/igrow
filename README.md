# igrow
service yang berfungsi untuk membuat Jadwal Rencana Pembayaran Pengembalian Dana dari sebuah Pembiayaan

# Requirements
- PHP => 7.2*
- valet
- mysql
- redis

# Deploy
- Composer install
- change .env-example file to .env
- php artisan migrate
- valet link

# Post
- url : igrow.test 
- request : POST
- Body : 
    - primary_payment [ex: 1000000000]
    - margin [ex: 12]
    - period_primary_payment [ex: 1]
    - period_margin_payment [ex: 1]
    - start_payment_date [format: YYYY-MM-DD] [ex: 2021-01-01]
    - tenor [ex: 12]

# Get
- url : igrow.test/{payment_id} [ex: igrow.test/ABCDE]
- request : Get
