import json
import requests
import html
import os
import sys
import time
from datetime import datetime, timedelta
import glob

def send_telegram_message(bot_token, chat_id, message, attempts=4):
    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    payload = {
        "chat_id": chat_id,
        "text": message,
        "parse_mode": "HTML"
    }
    result = {}
    for attempt in range(1, attempts + 1):
        try:
            response = requests.post(url, json=payload, timeout=(10, 30))
            result = response.json()
        except (requests.RequestException, ValueError) as e:
            # Don't leak the bot token, which is part of the URL, into the log
            result = {"ok": False, "description": str(e).replace(bot_token, '***')}
            response = None

        if result.get('ok'):
            return result
        if response is not None and response.status_code < 500 and response.status_code != 429:
            return result  # e.g. a formatting error, retrying won't help
        if attempt < attempts:
            retry_after = result.get('parameters', {}).get('retry_after')
            time.sleep(min(retry_after, 60) if retry_after else 5 * attempt)
    return result

def format_job_blocks(jobs):
    blocks = []
    for i, job in enumerate(jobs, 1):
        title = html.escape(str(job.get('Title', 'No Title')))
        link = html.escape(str(job.get('Link', '#')), quote=True)
        education = html.escape(str(job.get('Education Level', 'Not specified')))
        category = html.escape(str(job.get('Job Category', 'Not specified')))
        group = html.escape(str(job.get('Group Classification', 'Not specified')))

        block = f'{i}. <a href="{link}">{title}</a>\n'
        block += f"   Education Level: {education}\n"
        block += f"   Job Category: {category}\n"
        block += f"   Group Classification: {group}\n\n"
        blocks.append(block)
    return blocks

def split_messages(header, blocks, max_length=4000):
    """Pack whole job blocks into messages, so a link is never cut in half."""
    messages = []
    current = header
    for block in blocks:
        if len(current) + len(block) > max_length:
            messages.append(current)
            current = ''
        current += block
    if current:
        messages.append(current)
    return messages

def get_latest_jobs_file(directory='relevant_jobs'):
    pattern = os.path.join(directory, 'relevant_jobs_*.json')
    files = glob.glob(pattern)
    if not files:
        return None
    return max(files, key=os.path.getctime)

def is_file_recent(file_path, max_age_days=1):
    if not os.path.exists(file_path):
        return False
    file_time = datetime.fromtimestamp(os.path.getctime(file_path))
    return datetime.now() - file_time < timedelta(days=max_age_days)

def main():
    bot_token = os.environ.get('TELEGRAM_BOT_TOKEN')
    chat_id = os.environ.get('TELEGRAM_CHAT_ID')

    if not bot_token or not chat_id:
        print("Error: Telegram bot token or chat ID not provided in environment variables.")
        return

    latest_jobs_file = get_latest_jobs_file()
    if not latest_jobs_file or not is_file_recent(latest_jobs_file):
        print("No recent jobs file found. Skipping notification.")
        return

    try:
        with open(latest_jobs_file, 'r', encoding='utf-8') as f:
            jobs = json.load(f)
    except json.JSONDecodeError:
        print(f"Error: Unable to parse JSON from {latest_jobs_file}.")
        return

    if not jobs:
        print("No jobs found in the file. Skipping notification.")
        return

    header = f"<b>New Relevant Jobs Found - {len(jobs)} jobs</b>\n\n"
    # Telegram's max message length is 4096, we leave some buffer
    messages = split_messages(header, format_job_blocks(jobs))

    failed = 0
    for i, msg in enumerate(messages):
        if i:
            time.sleep(1)  # stay below Telegram's flood limits
        response = send_telegram_message(bot_token, chat_id, msg)
        if response.get('ok'):
            print(f"Message sent successfully. Using file: {latest_jobs_file}")
        else:
            failed += 1
            print(f"Failed to send message. Error: {response.get('description')}")

    if failed:
        sys.exit(f"{failed} of {len(messages)} Telegram messages could not be sent.")

if __name__ == "__main__":
    main()
