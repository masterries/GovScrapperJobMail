import json
import requests
import re
import os
from datetime import datetime, timedelta
import glob

def send_telegram_message(bot_token, chat_id, message):
    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    payload = {
        "chat_id": chat_id,
        "text": message,
        "parse_mode": "Markdown"
    }
    response = requests.post(url, json=payload)
    return response.json()

def escape_markdown(text):
    """Escape special characters for Markdown in Telegram."""
    return re.sub(r'([*_`\[\]()])', r'\\\1', str(text))

def format_job_message(jobs):
    message = f"*New Relevant Jobs Found - {len(jobs)} jobs*\n\n"
    for i, job in enumerate(jobs, 1):
        title = escape_markdown(job.get('Title', 'No Title'))
        link = escape_markdown(job.get('Link', '#'))
        education = escape_markdown(job.get('Education Level', 'Not specified'))
        category = escape_markdown(job.get('Job Category', 'Not specified'))
        group = escape_markdown(job.get('Group Classification', 'Not specified'))
        
        message += f"{i}. [{title}]({link})\n"
        message += f"   Education Level: {education}\n"
        message += f"   Job Category: {category}\n"
        message += f"   Group Classification: {group}\n\n"
    return message

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
        with open(latest_jobs_file, 'r') as f:
            jobs = json.load(f)
    except json.JSONDecodeError:
        print(f"Error: Unable to parse JSON from {latest_jobs_file}.")
        return

    if not jobs:
        print("No jobs found in the file. Skipping notification.")
        return

    message = format_job_message(jobs)

    # Split message if it's too long
    max_length = 4000  # Telegram's max message length is 4096, we leave some buffer
    messages = [message[i:i+max_length] for i in range(0, len(message), max_length)]

    for msg in messages:
        response = send_telegram_message(bot_token, chat_id, msg)
        if response.get('ok'):
            print(f"Message sent successfully. Using file: {latest_jobs_file}")
        else:
            print(f"Failed to send message. Error: {response.get('description')}")

if __name__ == "__main__":
    main()