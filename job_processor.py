import json
import re
import os
from datetime import datetime

def load_json(file_name):
    try:
        with open(file_name, 'r', encoding='utf-8') as f:
            return json.load(f)
    except FileNotFoundError:
        print(f"Error: File '{file_name}' not found.")
        return None
    except json.JSONDecodeError:
        print(f"Error: Unable to decode JSON in '{file_name}'.")
        return None

def search_keywords(job, keywords):
    # Only search in specific fields: Title, Education Level, Job Category, Status, Ministry
    relevant_fields = ['Title', 'Education Level', 'Job Category', 'Status', 'Ministry']
    
    # Create a text string containing only the relevant fields
    job_text = ' '.join(str(job.get(field, '')).lower() for field in relevant_fields)
    
    for keyword in keywords:
        for lang in ['en', 'fr', 'de']:
            if keyword.get(lang) and re.search(r'\b' + re.escape(keyword[lang].lower()) + r'\b', job_text):
                return True
    return False

def get_relevant_jobs(jobs, keywords):
    return [job for job in jobs if search_keywords(job, keywords)]

def save_relevant_jobs(relevant_jobs):
    date_str = datetime.now().strftime("%Y-%m-%d")
    folder_name = "relevant_jobs"
    if not os.path.exists(folder_name):
        os.makedirs(folder_name)
    
    file_name = f"{folder_name}/relevant_jobs_{date_str}.json"
    
    with open(file_name, 'w', encoding='utf-8') as f:
        json.dump(relevant_jobs, f, ensure_ascii=False, indent=4)
    
    return file_name

def main():
    jobs = load_json('new_jobs.json')
    keywords_data = load_json('keywords.json')

    if not jobs or not keywords_data:
        return

    keywords = keywords_data['keywords']
    relevant_jobs = get_relevant_jobs(jobs, keywords)
    file_name = save_relevant_jobs(relevant_jobs)
    
    print(f"Found {len(relevant_jobs)} relevant jobs. Saved to {file_name}")
    return file_name

if __name__ == "__main__":
    main()