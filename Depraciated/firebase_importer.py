#!/usr/bin/env python3
# firebase_importer.py - Importiert Jobs in Firebase Firestore
# Speziell optimiert für GitHub Actions Umgebung

import json
import os
import base64
import firebase_admin
from firebase_admin import credentials
from firebase_admin import firestore
from hashlib import md5
from datetime import datetime
import sys

# 1. Lade die JSON-Datei mit allen Jobs
def load_jobs_from_json(file_path='jobs_all_processed.json'):
    """Lädt Jobs aus einer JSON-Datei."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            jobs = json.load(f)
        print(f"Erfolgreich {len(jobs)} Jobs aus {file_path} geladen.")
        return jobs
    except Exception as e:
        print(f"Fehler beim Laden der JSON-Datei: {str(e)}")
        return []

# 2. Initialisiere Firebase mit Umgebungsvariable
def setup_firebase():
    """Initialisiert Firebase mit der Umgebungsvariable FIREBASE_SERVICE_ACCOUNT."""
    if firebase_admin._apps:
        return firestore.client()
        
    # In GitHub Actions wird die Umgebungsvariable verwendet
    if 'FIREBASE_SERVICE_ACCOUNT' in os.environ:
        try:
            service_account_base64 = os.environ['FIREBASE_SERVICE_ACCOUNT']
            service_account_json = base64.b64decode(service_account_base64)
            service_account_info = json.loads(service_account_json)
            cred = credentials.Certificate(service_account_info)
            firebase_admin.initialize_app(cred)
            print("Firebase mit Umgebungsvariable initialisiert.")
            return firestore.client()
        except Exception as e:
            print(f"Fehler bei der Verwendung der Umgebungsvariable: {str(e)}")
            return None
    else:
        print("FEHLER: Umgebungsvariable FIREBASE_SERVICE_ACCOUNT nicht gefunden.")
        print("Stelle sicher, dass du diese in deinen GitHub Secrets gesetzt hast.")
        return None

# 3. Ermittle vorhandene Job-IDs aus Firestore 
def get_existing_job_ids(db):
    """Holt die IDs aller bestehenden Jobs aus Firestore."""
    try:
        print("Lade bestehende Job-IDs aus Firestore...")
        
        existing_ids = set()
        jobs_ref = db.collection('jobs')
        
        # Nur die IDs abrufen
        docs = jobs_ref.stream()
        for doc in docs:
            existing_ids.add(doc.id)
        
        print(f"Es wurden {len(existing_ids)} bestehende Job-IDs gefunden.")
        return existing_ids
    except Exception as e:
        print(f"Fehler beim Abrufen existierender Job-IDs: {str(e)}")
        return set()

# 4. Importiere Jobs in Firestore mit Batch-Processing und Skip von vorhandenen Jobs
def import_jobs_to_firestore(jobs, batch_size=400):
    """Importiert Jobs nach Firestore und überspringt bereits existierende."""
    db = setup_firebase()
    if not db:
        print("Firebase konnte nicht initialisiert werden.")
        return 0
    
    # Vorhandene Job-IDs abrufen
    existing_ids = get_existing_job_ids(db)
    
    # Anhand der IDs Jobs filtern, die importiert werden müssen
    jobs_to_import = []
    skipped_jobs = 0
    
    print("Identifiziere neue Jobs für den Import...")
    for job in jobs:
        if 'Link' in job:
            job_id = md5(job['Link'].encode()).hexdigest()
            if job_id not in existing_ids:
                jobs_to_import.append(job)
            else:
                skipped_jobs += 1
    
    print(f"{len(jobs_to_import)} neue Jobs zum Importieren gefunden.")
    print(f"{skipped_jobs} Jobs werden übersprungen, da sie bereits in Firestore vorhanden sind.")
    
    if not jobs_to_import:
        print("Keine neuen Jobs zu importieren. Import wird übersprungen.")
        return 0
    
    print(f"Beginne Import von {len(jobs_to_import)} neuen Jobs in Firestore...")
    total_imported = 0
    batches = 0
    
    # Führe den Import in Batches durch
    for i in range(0, len(jobs_to_import), batch_size):
        batch = db.batch()
        current_batch = jobs_to_import[i:i+batch_size]
        added_to_batch = 0
        
        print(f"Verarbeite Batch {batches+1} mit {len(current_batch)} Jobs...")
        
        for index, job in enumerate(current_batch):
            try:
                # In GitHub Actions ist es wichtig, den Fortschritt anzuzeigen
                if index > 0 and index % 50 == 0:
                    print(f"  Fortschritt: {index}/{len(current_batch)} Jobs")
                
                # Erstelle eine eindeutige ID für den Job basierend auf dem Link
                job_id = md5(job['Link'].encode()).hexdigest()
                
                # Stelle sicher, dass adding_date existiert
                if 'adding_date' not in job:
                    job['adding_date'] = datetime.now().isoformat()
                
                # Füge die Dokument-ID zum Job hinzu
                job['firestore_id'] = job_id
                
                # Füge zum Batch hinzu
                doc_ref = db.collection('jobs').document(job_id)
                batch.set(doc_ref, job)
                added_to_batch += 1
                
            except Exception as e:
                print(f"Fehler bei Job {job.get('Title', 'Unbekannt')}: {str(e)}")
        
        # Commit den Batch
        if added_to_batch > 0:
            try:
                batch.commit()
                batches += 1
                total_imported += added_to_batch
                print(f"Batch {batches} abgeschlossen: {added_to_batch} Jobs importiert")
            except Exception as e:
                print(f"Fehler beim Commit von Batch {batches+1}: {str(e)}")
    
    print(f"Import abgeschlossen. Insgesamt {total_imported} neue Jobs in {batches} Batches importiert.")
    print(f"{skipped_jobs} vorhandene Jobs wurden übersprungen.")
    return total_imported

# 5. Überprüfe den Erfolg mit einer Abfrage
def verify_import(limit=5):
    """Bestätigt den erfolgreichen Import durch Abfrage von Beispiel-Jobs."""
    db = setup_firebase()
    if not db:
        return
    
    print("Prüfe Ergebnis des Imports...")
    
    # In GitHub Actions wollen wir keine unnötigen Abfragen machen
    # Prüfen wir nur eine Stichprobe
    jobs_ref = db.collection('jobs')
    
    try:
        # Anzahl der Jobs ermitteln
        count_query = jobs_ref.count()
        count_result = count_query.get()
        jobs_count = count_result[0][0].value
        print(f"Anzahl Jobs in Firestore: {jobs_count}")
    except Exception as e:
        print(f"Hinweis: Konnte Anzahl nicht ermitteln - {str(e)}")
        print("Fahre mit Stichprobe fort...")
    
    print("\nStichprobe (bis zu 5 Jobs):")
    try:
        sample_jobs = list(jobs_ref.limit(limit).stream())
        for doc in sample_jobs:
            job_data = doc.to_dict()
            print(f"- {job_data.get('Title', 'Kein Titel')}")
            print(f"  ID: {doc.id}")
    except Exception as e:
        print(f"Fehler bei der Stichprobe: {str(e)}")

def main():
    """Hauptfunktion des Importers."""
    print("Firebase Job Importer gestartet...")
    
    # Überprüfe welche JSON-Datei importiert werden soll
    file_path = 'jobs_all_processed.json'
    if len(sys.argv) > 1:
        file_path = sys.argv[1]
        print(f"Verwende angegebene Datei: {file_path}")
    
    # Lade die Jobs aus der JSON-Datei
    jobs = load_jobs_from_json(file_path)
    
    # Wenn Jobs vorhanden sind, in Firestore importieren
    if jobs:
        imported_count = import_jobs_to_firestore(jobs)
        
        # Überprüfe den Erfolg
        if imported_count > 0:
            verify_import()
        elif imported_count == 0:
            print("Keine neuen Jobs importiert. Alle Jobs sind bereits vorhanden.")
    else:
        print("Keine Jobs zum Importieren gefunden.")
        sys.exit(1)  # Exit mit Fehlercode für GitHub Actions

if __name__ == "__main__":
    main()