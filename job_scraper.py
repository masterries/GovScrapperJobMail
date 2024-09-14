import requests
from bs4 import BeautifulSoup
import json
from datetime import datetime
import os
import time  # Import the time module for adding delay

# JSON configuration
json_config = {
    "column_mapping": {
        "Titel": "Title",
        "Link": "Link",
        "Niveau d'études": "Education Level",
        "Catégorie de métiers": "Job Category",
        "Statut": "Status",
        "Tâche": "Task",
        "Ministère": "Ministry",
        "Administration/Organisme": "Administration/Organization",
        "Date limite de candidature": "Application Deadline",
        "Groupe de traitement": "Treatment Group",
        "Nationalité": "Nationality",
        "Nombre de postes vacants": "Number of Vacancies",
        "Groupe d'indemnité": "Compensation Group",
        "Type de contrat": "Contract Type",
        "Groupe": "Group",
        "Région": "Region",
        "Commune/Syndicat de communes": "Municipality/Syndicate of Municipalities",
        "Groupe de salaire": "Salary Group"
    },
    "group_fields": {
        "Group Classification": ["Treatment Group", "Compensation Group", "Group", "Salary Group"],
        "Location": ["Region", "Municipality/Syndicate of Municipalities"]
    }
}

def scrape_jobs():
    base_url = 'https://govjobs.public.lu'
    start_url = 'https://govjobs.public.lu/fr/rechercher-parmi-offres-emploi.html'
    all_jobs = []
    page_number = 0

    while True:
        url = f'{start_url}?b={page_number * 20}'
        print(f'Scraping page: {url}')

        response = requests.get(url)
        soup = BeautifulSoup(response.content, 'html.parser')
        articles = soup.find_all('article', class_='article search-result search-result--job')

        if not articles:
            break

        for article in articles:
            job = {}
            title_tag = article.find('h2', class_='article-title')
            if title_tag and title_tag.find('a'):
                a_tag = title_tag.find('a')
                job['Titel'] = a_tag.text.strip()
                link = a_tag['href']
                if link.startswith('//'):
                    link = 'https:' + link
                elif link.startswith('/'):
                    link = base_url + link
                else:
                    link = base_url + '/' + link.lstrip('/')
                job['Link'] = link

            footer = article.find('footer', class_='article-metas')
            if footer:
                meta_list = footer.find('ul', class_='list--inline list--dotted')
                if meta_list:
                    for item in meta_list.find_all('li'):
                        text = item.get_text(separator=' ').strip()
                        key, value = text.split(': ', 1) if ': ' in text else (text, '')
                        job[key] = value

            custom_list = article.find('ul', class_='nude article-custom')
            if custom_list:
                for item in custom_list.find_all('li'):
                    span, b_tag = item.find('span'), item.find('b')
                    if span and b_tag:
                        job[span.text.strip()] = b_tag.text.strip()

            all_jobs.append(job)

        page_number += 1
        time.sleep(2)  # Add a 2-second delay between page scrapes

    return process_jobs(all_jobs)

def process_jobs(jobs):
    processed_jobs = []
    for job in jobs:
        processed_job = {}
        for fr_key, value in job.items():
            en_key = json_config['column_mapping'].get(fr_key, fr_key)
            processed_job[en_key] = value
        
        for group_name, fields in json_config['group_fields'].items():
            group_value = next((processed_job[field] for field in fields if field in processed_job), None)
            if group_value:
                processed_job[group_name] = group_value
                for field in fields:
                    processed_job.pop(field, None)
        
        processed_job['adding_date'] = datetime.now().isoformat()
        processed_jobs.append(processed_job)
    
    return processed_jobs

def update_json(new_jobs, filename='jobs_all_processed.json'):
    if os.path.exists(filename):
        with open(filename, 'r', encoding='utf-8') as f:
            existing_jobs = json.load(f)
    else:
        existing_jobs = []

    existing_links = {job['Link'] for job in existing_jobs}
    
    updated_jobs = existing_jobs.copy()
    new_jobs_added = []

    for job in new_jobs:
        if job['Link'] not in existing_links:
            updated_jobs.append(job)
            new_jobs_added.append(job)
            existing_links.add(job['Link'])

    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(updated_jobs, f, ensure_ascii=False, indent=4)

    return new_jobs_added

def save_new_jobs(new_jobs, filename='new_jobs.json'):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(new_jobs, f, ensure_ascii=False, indent=4)

def main():
    scraped_jobs = scrape_jobs()
    new_jobs = update_json(scraped_jobs)
    save_new_jobs(new_jobs)
    print(f"Scraping completed. {len(scraped_jobs)} jobs processed.")
    print(f"{len(new_jobs)} new jobs added to 'jobs_all_processed.json' and saved to 'new_jobs.json'")

if __name__ == "__main__":
    main()
