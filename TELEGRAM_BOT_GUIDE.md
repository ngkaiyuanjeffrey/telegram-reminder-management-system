# 🤖 Telegram Bot Setup & Chat ID Guide

This guide explains how to create your Telegram Bot using **@BotFather**, obtain the **Bot API Token**, and find **Telegram Chat IDs** for your recipients.

---

## 📌 Step 1: Create a Bot via @BotFather

1. Open your Telegram app (Desktop, Mobile, or Web).
2. Search for the official verified bot: **`@BotFather`** (or open [https://t.me/BotFather](https://t.me/BotFather)).
3. Click **Start** or send the command:
   ```text
   /start
   ```
4. Create a new bot by sending:
   ```text
   /newbot
   ```
5. Follow the prompts:
   - **Bot Name**: Enter a display name (e.g. `My Company Reminder Bot`).
   - **Bot Username**: Enter a unique username ending in `bot` (e.g. `my_company_reminder_bot`).
6. **Save your Bot Token**:
   @BotFather will reply with your API token formatted like:
   ```text
   7123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ123456
   ```
   > [!IMPORTANT]
   > Keep this token private! Paste it into **Admin Portal &rarr; Bot & Settings &rarr; Telegram Bot Token**.

---

## 👥 Step 2: How Recipients Get Their Telegram Chat ID

> [!NOTE]
> Telegram requires recipients to initiate contact with your bot before the bot can send them scheduled messages.

### Method A: Discover Chat IDs Automatically (Built-in Feature)
1. Ask your users to open your bot in Telegram and click **`/start`**.
2. Go to **Admin Portal &rarr; Bot & Settings &rarr; Discover Telegram Chat IDs**.
3. Click **"Check Recent Bot Messages"**.
4. The system will display all recent incoming users with their Name, Username, and Chat ID, with a 1-click **"+ Add User"** button!

### Method B: Using @userinfobot (Manual Method)
1. Tell the recipient to search for **`@userinfobot`** in Telegram.
2. Click **Start** or send any message.
3. The bot will immediately reply with their user details:
   ```text
   Id: 987654321
   First: John
   Last: Doe
   Lang: en
   ```
4. Copy the numerical **`Id`** (`987654321`) and add it to **Admin Portal &rarr; Telegram Users &rarr; Add Recipient**.

---

## ✍️ Step 3: Message Formatting Guide (HTML Support)

The Telegram Reminder Management System supports rich HTML formatting tags in message sequences:

| HTML Tag | Description | Example |
|---|---|---|
| `<b>Bold Text</b>` | Bold font weight | <b>Meeting at 10 AM</b> |
| `<i>Italic Text</i>` | Italicized emphasis | <i>Please be on time</i> |
| `<code>Code / Highlight</code>` | Monospace code block | <code>ORDER-12345</code> |
| `<pre>Block Code</pre>` | Preformatted multi-line text | `<pre>Line 1\nLine 2</pre>` |
| `<a href="https://example.com">Link</a>` | Hyperlink URL | `<a href="https://zoom.us">Join Zoom Call</a>` |
| `<s>Strikethrough</s>` | Strikethrough text | `<s>Old Price</s>` |
| `<u>Underline</u>` | Underlined text | `<u>Important Note</u>` |

---

## ⚡ Sequential Message Example

When creating a reminder in the admin dashboard, you can build multiple messages sent in sequence:

```text
Message 1: 🌅 <b>Good Morning!</b> Hope you are having a productive day.
Message 2: 📌 <b>Reminder:</b> Your monthly report submission is due by 5:00 PM.
Message 3: 🔗 Please upload your files here: <a href="https://portal.company.com/upload">Submit Report</a>
```
The system will automatically dispatch Message 1 &rarr; *wait 2 seconds* &rarr; Message 2 &rarr; *wait 2 seconds* &rarr; Message 3!
