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

def scrape_job_details(url):
    """Scrape detailed information from the job's detail page"""
    print(f'Scraping details: {url}')
    job_details = {}
    
    try:
        response = requests.get(url)
        if response.status_code != 200:
            print(f"Failed to get details from {url}: Status code {response.status_code}")
            return job_details
            
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Find the main content div that contains the detailed job description
        page_text_div = soup.find('div', class_='page-text')
        if not page_text_div:
            return job_details
            
        # Extract all text content from the page-text div
        job_details['Full Description'] = page_text_div.get_text(separator=' ', strip=True)
        
        # Try to extract structured data from the page-text div
        # Look for headers/titles followed by content
        headers = page_text_div.find_all(['h2', 'h3', 'h4', 'strong', 'b'])
        for header in headers:
            header_text = header.get_text(strip=True)
            if header_text and len(header_text) > 2:  # Skip very short headers
                # Try to find the content associated with this header
                content = []
                for sibling in header.next_siblings:
                    # Stop at the next header or when we've reached significant content
                    if sibling.name in ['h2', 'h3', 'h4', 'strong', 'b']:
                        break
                    if sibling.name == 'p' or sibling.name == 'ul' or sibling.name == 'div':
                        content.append(sibling.get_text(strip=True))
                
                if content:
                    job_details[header_text] = ' '.join(content)
        
        # Try to extract any table data if present
        tables = page_text_div.find_all('table')
        for i, table in enumerate(tables):
            table_data = []
            rows = table.find_all('tr')
            for row in rows:
                cells = row.find_all(['td', 'th'])
                row_data = [cell.get_text(strip=True) for cell in cells]
                if len(row_data) >= 2:
                    job_details[row_data[0]] = row_data[1]
                elif row_data:
                    table_data.append(row_data)
            
            if table_data and i == 0:
                job_details['Table Data'] = table_data
        
    except Exception as e:
        print(f"Error scraping details from {url}: {str(e)}")
    
    return job_details

def scrape_jobs():
    base_url = 'https://govjobs.public.lu'
    start_url = 'https://govjobs.public.lu/fr/rechercher-parmi-offres-emploi.html'
    all_jobs = []
    page_number = 0
    
    # Load existing jobs to check if we need to fetch details
    existing_jobs_dict = {}
    if os.path.exists('jobs_all_processed.json'):
        try:
            with open('jobs_all_processed.json', 'r', encoding='utf-8') as f:
                existing_jobs = json.load(f)
                existing_jobs_dict = {job['Link']: job for job in existing_jobs if 'Link' in job}
                print(f"Loaded {len(existing_jobs_dict)} existing jobs for reference")
        except Exception as e:
            print(f"Error loading existing jobs: {str(e)}")

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
            
            # Get detailed information from the job page if needed
            if 'Link' in job:
                existing_job = existing_jobs_dict.get(job['Link'])
                
                # Check if we need to fetch details
                needs_details = True
                if existing_job:
                    # Check if the existing job already has detailed info
                    if 'Full Description' in existing_job or any(key.startswith('Section:') for key in existing_job):
                        needs_details = False
                        print(f"Using cached details for: {job.get('Titel', job['Link'])}")
                
                if needs_details:
                    # Add a delay between requests to avoid overloading the server
                    time.sleep(1)
                    print(f"Fetching details for: {job.get('Titel', job['Link'])}")
                    job_details = scrape_job_details(job['Link'])
                    
                    # Merge the details with the main job data
                    for key, value in job_details.items():
                        if key not in job:  # Don't overwrite existing data
                            job[key] = value
                elif existing_job:
                    # Copy the detailed fields from the existing job
                    for key, value in existing_job.items():
                        if key not in job and key != 'adding_date':
                            job[key] = value

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
    existing_jobs_dict = {job['Link']: job for job in existing_jobs}
    
    updated_jobs = existing_jobs.copy()
    new_jobs_added = []

    for job in new_jobs:
        if job['Link'] not in existing_links:
            # This is a completely new job
            updated_jobs.append(job)
            new_jobs_added.append(job)
            existing_links.add(job['Link'])
        else:
            # The job exists, but check if we need to update with new detail info
            existing_job = existing_jobs_dict[job['Link']]
            
            # Check if the job has any new fields from the detail page that the existing one doesn't
            has_new_details = False
            for key, value in job.items():
                if key not in existing_job and key != 'adding_date':
                    has_new_details = True
                    break
            
            if has_new_details:
                # Update the existing job with new details while preserving the original adding_date
                original_date = existing_job.get('adding_date')
                for key, value in job.items():
                    if key != 'adding_date':  # Don't overwrite the original adding_date
                        existing_job[key] = value
                if original_date:
                    existing_job['adding_date'] = original_date
                existing_job['updated_date'] = datetime.now().isoformat()

    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(updated_jobs, f, ensure_ascii=False, indent=4)

    return new_jobs_added

def save_new_jobs(new_jobs, filename='new_jobs.json'):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(new_jobs, f, ensure_ascii=False, indent=4)

def test_scrape_details(num_jobs=2):
    """
    Test function to scrape only a limited number of jobs with their details
    for testing purposes.
    
    Args:
        num_jobs: Number of jobs to scrape (default 2)
    """
    base_url = 'https://govjobs.public.lu'
    start_url = 'https://govjobs.public.lu/fr/rechercher-parmi-offres-emploi.html'
    test_jobs = []
    
    # Get the first page only
    print(f'Test scraping: {start_url}')
    response = requests.get(start_url)
    soup = BeautifulSoup(response.content, 'html.parser')
    articles = soup.find_all('article', class_='article search-result search-result--job')
    
    # Limit to the specified number of jobs
    articles = articles[:num_jobs]
    
    for article in articles:
        job = {}
        title_tag = article.find('h2', class_='article-title')
        if title_tag and title_tag.find('a'):
            a_tag = title_tag.find('a')
            job['Title'] = a_tag.text.strip()
            link = a_tag['href']
            if link.startswith('//'):
                link = 'https:' + link
            elif link.startswith('/'):
                link = base_url + link
            else:
                link = base_url + '/' + link.lstrip('/')
            job['Link'] = link
            
            # Get the job details
            print(f'Testing detail scraping for: {job["Title"]}')
            job_details = scrape_job_details(job['Link'])
            
            # Merge the details with the basic job data
            job.update(job_details)
            
            test_jobs.append(job)
    
    # Save the test results to a file
    test_file = 'test_job_details.json'
    with open(test_file, 'w', encoding='utf-8') as f:
        json.dump(test_jobs, f, ensure_ascii=False, indent=4)
    
    print(f"Test completed. {len(test_jobs)} jobs processed and saved to {test_file}")
    return test_jobs

def main():
    # Uncomment the test function to run in test mode
    # test_scrape_details(2)
    # return
    
    scraped_jobs = scrape_jobs()
    new_jobs = update_json(scraped_jobs)
    save_new_jobs(new_jobs)
    print(f"Scraping completed. {len(scraped_jobs)} jobs processed.")
    print(f"{len(new_jobs)} new jobs added to 'jobs_all_processed.json' and saved to 'new_jobs.json'")

if __name__ == "__main__":
    # For testing, uncomment this line:
    # test_scrape_details(2)
    
    # For regular operation, keep this line:
    main()