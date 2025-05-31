# BudgetBot

A personal Telegram bot built with Laravel for effortless cash flow tracking and expense management. This project serves as a practical sandbox for refining my software development skills during my free time, while also providing a hands-on tool for personal finance tracking.
___

### Key features 
- Easy Expense & Income Tracking: Simply send a message to the bot to record your transactions. For example, typing Ingreso 1000 sueldo will save 1000 as an income with the category "sueldo".
- User & Chat ID Logging: Automatically captures your Telegram chat ID and name for personalized tracking.
- Laravel Ecosystem: Built on a robust and scalable framework.
- Development Tools: Includes Laravel Telescope for debugging and insight into application requests, entries, commands, and more during development.
---

### Technologies Used

- Laravel
- PHP
- Telegram Bot API
- MySQL (or your preferred database)
- Laravel Telescope (for local development)
----

### Installation & Setup

1. Clone the repository:

    ```bash
    git clone https://github.com/migithub/budget-bot.git

    cd budget-bot
    ```

2. Install PHP dependencies:

    ```bash 
    composer install
    ```

3. Copy environment file:

    ```bash
    cp .env.example .env
    ```

4. Configure .env:

   - Set your database credentials.
   - Add your Telegram Bot Token:
    
    ```bash 
    TELEGRAM_BOT_TOKEN="YOUR_TELEGRAM_BOT_TOKEN_HERE"
    ```
    - Enable Telescope for development:

    ```bash
    TELESCOPE_LOG_WATCHER=true
    ```
5. Generate application key:

    ```bash
    php artisan key:generate
    ```
6. Run database migrations:

    ```bash
    php artisan migrate
    ```

7. Link storage:

    ```bash
    php artisan storage:link
    ```

8. Start local server (for development):

    ```bash
    php artisan serve
    ```
    
    You'll also need a way to expose your local server to the internet for Telegram webhooks (e.g., ngrok).
___


### How to Use the Bot

Once your bot is running and configured with Telegram, you can interact with it via Telegram:

- Record an expense: Send a message like Gasto 500 comida
- Record an income: Send a message like Ingreso 1000 sueldo
- To end the month, type: cierre 
---

### Contributing

Feel free to explore the code, open issues, or suggest improvements. This is a personal learning project, and contributions are welcome!

---

### License

This project is open-sourced software licensed under the MIT license.

---

### Contact

GitHub: maracev

---