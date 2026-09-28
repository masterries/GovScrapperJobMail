import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
from bs4 import BeautifulSoup
import json
from datetime import datetime
import os
import random
import re
import sys
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

BASE_URL = 'https://govjobs.public.lu'
START_URL = 'https://govjobs.public.lu/fr/rechercher-parmi-offres-emploi.html'

# Network settings. govjobs.public.lu refuses TCP connections ("Connection refused") when a
# client opens too many of them, so all requests share one keep-alive session. The server
# closes idle connections after 5 seconds, so the delays between requests stay below that.
REQUEST_TIMEOUT = (10, 30)   # (connect, read) in seconds
LISTING_DELAY = 2            # seconds between listing pages (plus up to 1s jitter)
DETAIL_DELAY = 2             # seconds between detail pages (plus up to 1s jitter)
MAX_PAGES = 100              # safety net against endless pagination
MAX_DETAIL_FETCHES = 100     # per run; the rest is fetched on the next run
MAX_DETAIL_FAILURES = 3      # consecutive failed detail pages before details are paused for this run
TIME_BUDGET = 20 * 60        # seconds; afterwards the scraper stops and saves what it has
RETRY_STATUSES = (429, 500, 502, 503, 504)


class CappedRetry(Retry):
    """Retry that never waits longer than 60 seconds, even if the server sends a larger Retry-After."""

    def get_retry_after(self, response):
        retry_after = super().get_retry_after(response)
        return None if retry_after is None else min(retry_after, 60)


def create_session():
    # Refused connections and 429/5xx answers are retried after ~0, 6, 12, 24 and 48 seconds
    retry = CappedRetry(
        total=5,
        connect=5,
        read=2,
        status=3,
        backoff_factor=3,
        backoff_max=60,
        backoff_jitter=1,
        status_forcelist=RETRY_STATUSES,
        allowed_methods=frozenset({'GET'}),
        raise_on_status=False,
    )
    session = requests.Session()
    session.mount('https://', HTTPAdapter(max_retries=retry))
    session.headers.update({
        'User-Agent': 'GovScrapperJobMail/1.0 (+https://github.com/masterries/GovScrapperJobMail)',
        'Accept': 'text/html,application/xhtml+xml',
        'Accept-Language': 'fr,en;q=0.8',
    })
    return session


session = create_session()


def polite_sleep(seconds):
    time.sleep(seconds + random.uniform(0, 1))


def scrape_job_details(url):
    """Scrape detailed information from the job's detail page.

    Raises requests.RequestException when the page could not be fetched (refused connection,
    timeout, 429/5xx after all retries) so the caller can back off.
    """
    print(f'Scraping details: {url}')
    job_details = {}

    response = session.get(url, timeout=REQUEST_TIMEOUT)
    if response.status_code in (404, 410):
        print(f"Failed to get details from {url}: Status code {response.status_code}")
        return job_details
    response.raise_for_status()

    try:
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
        print(f"Error parsing details from {url}: {str(e)}")

    return job_details

def parse_result_count(soup):
    """Number of jobs the search page says it has, or None if it can't be read."""
    count_tag = soup.find(class_='search-meta-count')
    if not count_tag:
        return None
    match = re.search(r'\d+', count_tag.get_text().replace(' ', '').replace('\xa0', ''))
    return int(match.group()) if match else None

def parse_listing_article(article):
    job = {}
    title_tag = article.find('h2', class_='article-title')
    a_tag = title_tag.find('a') if title_tag else None
    if not a_tag or not a_tag.get('href'):
        return None

    job['Titel'] = a_tag.text.strip()
    link = a_tag['href']
    if link.startswith('//'):
        link = 'https:' + link
    elif link.startswith('/'):
        link = BASE_URL + link
    else:
        link = BASE_URL + '/' + link.lstrip('/')
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

    return job

def scrape_listing(deadline):
    """Collect all jobs from the search result pages.

    Returns (jobs, complete); complete is False when the crawl had to stop early.
    """
    jobs = []
    seen_links = set()
    expected_total = None

    for page_number in range(MAX_PAGES):
        if time.monotonic() > deadline:
            print('::warning::Time budget exceeded while crawling the listing pages')
            return jobs, False
        if page_number:
            polite_sleep(LISTING_DELAY)

        url = f'{START_URL}?b={page_number * 20}'
        print(f'Scraping page: {url}')
        try:
            response = session.get(url, timeout=REQUEST_TIMEOUT)
            response.raise_for_status()
        except requests.RequestException as e:
            print(f'::warning::Listing crawl aborted at {url}: {e}')
            return jobs, False

        soup = BeautifulSoup(response.content, 'html.parser')
        if expected_total is None:
            expected_total = parse_result_count(soup)
        articles = soup.find_all('article', class_='article search-result search-result--job')

        if not articles:
            if not soup.find('ol', class_='search-results'):
                # Not a regular (empty) result page, e.g. a maintenance or error page
                print(f'::warning::Unexpected page without search results at {url}')
                return jobs, False
            break

        new_on_page = 0
        for article in articles:
            job = parse_listing_article(article)
            if not job:
                print(f'Skipping a search result without link on {url}')
                continue
            if job['Link'] in seen_links:
                continue  # the listing shifted while crawling
            seen_links.add(job['Link'])
            jobs.append(job)
            new_on_page += 1

        if new_on_page == 0:
            break  # the site repeats pages we already have
    else:
        print(f'::warning::Stopped after {MAX_PAGES} listing pages')
        return jobs, False

    if expected_total is not None and len(jobs) < expected_total:
        print(f'::warning::The listing shows {expected_total} jobs but only {len(jobs)} were collected')
    return jobs, True

def add_job_details(jobs, existing_jobs_dict, deadline):
    """Add the detail page data to the listed jobs, from the cache where possible.

    Returns False when some missing details could not be fetched in this run. Those jobs are
    saved without 'Full Description' and fetched again on the next run.
    """
    complete = True
    fetches = 0
    consecutive_failures = 0

    for job in jobs:
        existing_job = existing_jobs_dict.get(job['Link'])

        # Check if the existing job already has detailed info
        if existing_job and ('Full Description' in existing_job or any(key.startswith('Section:') for key in existing_job)):
            print(f"Using cached details for: {job.get('Titel', job['Link'])}")
            # Copy the detailed fields from the existing job
            for key, value in existing_job.items():
                if key not in job and key != 'adding_date':
                    job[key] = value
            continue

        if not complete:
            continue
        if fetches >= MAX_DETAIL_FETCHES or time.monotonic() > deadline:
            print('::warning::Detail fetch limit reached; the remaining details are fetched on the next run')
            complete = False
            continue

        # Add a delay between requests to avoid overloading the server
        polite_sleep(DETAIL_DELAY)
        print(f"Fetching details for: {job.get('Titel', job['Link'])}")
        fetches += 1
        try:
            job_details = scrape_job_details(job['Link'])
        except requests.RequestException as e:
            print(f"Error scraping details from {job['Link']}: {str(e)}")
            consecutive_failures += 1
            if consecutive_failures >= MAX_DETAIL_FAILURES:
                print(f'::warning::{consecutive_failures} detail pages failed in a row; the remaining details are fetched on the next run')
                complete = False
            continue
        consecutive_failures = 0

        # Merge the details with the main job data
        for key, value in job_details.items():
            if key not in job:  # Don't overwrite existing data
                job[key] = value

    return complete

def scrape_jobs():
    """Scrape the listing, then the missing detail pages.

    Returns (processed_jobs, complete).
    """
    deadline = time.monotonic() + TIME_BUDGET

    # Load existing jobs to check if we need to fetch details
    existing_jobs_dict = {}
    if os.path.exists('jobs_all_processed.json'):
        with open('jobs_all_processed.json', 'r', encoding='utf-8') as f:
            existing_jobs = json.load(f)
        existing_jobs_dict = {job['Link']: job for job in existing_jobs if 'Link' in job}
        print(f"Loaded {len(existing_jobs_dict)} existing jobs for reference")

    # Listing first, so a blocked detail page can't cost us the list of new jobs
    jobs, listing_complete = scrape_listing(deadline)
    details_complete = add_job_details(jobs, existing_jobs_dict, deadline)

    return process_jobs(jobs), listing_complete and details_complete

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
            existing_jobs_dict[job['Link']] = job
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
    test_jobs = []

    # Get the first page only
    print(f'Test scraping: {START_URL}')
    response = session.get(START_URL, timeout=REQUEST_TIMEOUT)
    response.raise_for_status()
    soup = BeautifulSoup(response.content, 'html.parser')
    articles = soup.find_all('article', class_='article search-result search-result--job')

    # Limit to the specified number of jobs
    articles = articles[:num_jobs]

    for article in articles:
        job = parse_listing_article(article)
        if job:
            job['Title'] = job.pop('Titel')

            # Get the job details
            print(f'Testing detail scraping for: {job["Title"]}')
            polite_sleep(DETAIL_DELAY)
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

    scraped_jobs, complete = scrape_jobs()
    if not scraped_jobs:
        print('::error::No jobs could be scraped, nothing was saved')
        sys.exit(1)

    new_jobs = update_json(scraped_jobs)
    save_new_jobs(new_jobs)
    print(f"Scraping completed. {len(scraped_jobs)} jobs processed.")
    print(f"{len(new_jobs)} new jobs added to 'jobs_all_processed.json' and saved to 'new_jobs.json'")
    if not complete:
        # Partial data is still saved and committed; the rest is picked up on the next run
        print('::warning::Scraping was incomplete, the missing jobs/details are fetched on the next run')

if __name__ == "__main__":
    # For testing, uncomment this line:
    # test_scrape_details(2)

    # For regular operation, keep this line:
    main()
