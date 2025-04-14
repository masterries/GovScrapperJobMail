import dash
from dash import dcc, html, dash_table
import dash_bootstrap_components as dbc
import pandas as pd
import plotly.express as px
import json
import re
from datetime import datetime

# Initialize Dash app
app = dash.Dash(__name__, external_stylesheets=[dbc.themes.BOOTSTRAP])
app.title = "Luxembourg Government Jobs Analysis"

# Funktion zum Extrahieren des Basis-Titels
def extract_base_title(title):
    if pd.isna(title):
        return ""
    title = str(title)
    # Entferne alles in Klammern
    base_title = re.sub(r'\([^)]*\)', '', title)
    # Entferne "- Recrutement interne" und ähnliche Suffixe
    base_title = re.sub(r'-\s*Recrutement\s*interne', '', base_title, flags=re.IGNORECASE)
    # Bereinige zusätzliche Leerzeichen
    base_title = re.sub(r'\s+', ' ', base_title).strip()
    return base_title

# Laden der Beispieldaten
sample_data = [
    {"Title": "Agent comptable (m/f) (réf. M00031348) - Recrutement interne", "Link": "https://govjobs.public.lu/fr/postuler/mobilite-interne/changement-administration/2024/B1/Septembre/20240909-agentcomptablemfrfm00031348-313471.html", "Education Level": "Diplôme de fin d'études secondaires ou diplôme de technicien", "Job Category": "Affaires générales", "Status": "Fonctionnaire", "Task": "Tâche complète", "Ministry": "Ministère de l'Éducation nationale, de l'Enfance et de la Jeunesse", "Administration/Organization": "Lycée Technique d'Ettelbruck", "Application Deadline": "23/09/2024", "Nationality": "Être ressortissant UE", "Number of Vacancies": "1", "Group Classification": "B1", "adding_date": "2024-09-14T23:27:57.939801"},
    {"Title": "Juriste (m/f) (réf. M00031089) - Recrutement interne", "Link": "https://govjobs.public.lu/fr/postuler/mobilite-interne/changement-administration/2024/A1/Aout/20240819-juristemfrfm00031089-309422.html", "Education Level": "Master", "Job Category": "Affaires juridiques et Contentieux", "Status": "Fonctionnaire", "Task": "Tâche complète", "Ministry": "Ministère de l'Économie", "Administration/Organization": "Département ministériel", "Application Deadline": "19/09/2024", "Nationality": "Être ressortissant UE", "Number of Vacancies": "1", "Group Classification": "A1", "adding_date": "2024-09-14T23:27:57.939819"},
    {"Title": "Éducateur (m/f)", "Link": "https://govjobs.public.lu/fr/postuler/postes-ouverts/postes-vacants/decentralise/2024/B1/Septembre/20240905-ducateurmf-313022.html", "Education Level": "Diplôme de fin d'études secondaires ou diplôme de technicien", "Job Category": "Education et Formation tout au long de la vie", "Status": "Employé de l'État", "Task": "Tâche à temps partiel (25%)", "Ministry": "Ministère de l'Éducation nationale, de l'Enfance et de la Jeunesse", "Administration/Organization": "École préscolaire et primaire de recherche fondée sur la pédagogie inclusive "Eis Schoul"", "Application Deadline": "03/10/2024", "Nationality": "Être ressortissant UE", "Number of Vacancies": "1", "Contract Type": "CDD jusqu'au 14 septembre 2025", "Group Classification": "B1", "adding_date": "2024-09-14T23:27:57.939826"},
    {"Title": "Spécialiste en sciences humaines (m/f)", "Link": "https://govjobs.public.lu/fr/postuler/postes-ouverts/postes-vacants/decentralise/2024/A2/Septembre/20240910-spcialisteenscienceshumainesmf-313905.html", "Education Level": "Bachelor", "Job Category": "Education et Formation tout au long de la vie", "Status": "Employé de l'État", "Task": "Tâche complète", "Ministry": "Ministère de l'Éducation nationale, de l'Enfance et de la Jeunesse", "Administration/Organization": "Centre pour le développement des compétences relatives à la vue", "Application Deadline": "24/09/2024", "Nationality": "Être ressortissant UE", "Number of Vacancies": "1", "Contract Type": "CDI", "Group Classification": "A2", "adding_date": "2024-09-14T23:27:57.939832"}
]

# Erstelle DataFrame aus den Beispieldaten
df = pd.DataFrame(sample_data)

# Bereinige Datumsspalten
if "adding_date" in df.columns:
    df["adding_date"] = pd.to_datetime(df["adding_date"])

if "Application Deadline" in df.columns:
    df["Application Deadline"] = pd.to_datetime(df["Application Deadline"], format="%d/%m/%Y", errors="coerce")

# Extrahiere Basis-Titel
df['Base Title'] = df['Title'].apply(extract_base_title)

# Vereinfachte Verarbeitung - alle als einzigartige Jobs betrachten für die Demo
df['is_duplicate'] = False
unique_jobs = df.copy()

# App Layout
app.layout = dbc.Container([
    html.H1("🇱🇺 Luxembourg Government Jobs Analysis", className="mt-4 mb-2"),
    html.P("Analyse von Stellenangeboten des luxemburgischen Regierungsportals"),
    
    dbc.Alert(
        f"Daten geladen: {len(df)} Stellenangebote",
        color="success"
    ),
    
    dbc.Tabs([
        # Übersichts-Tab
        dbc.Tab(label="📊 Übersicht", children=[
            html.H3("Übersicht", className="mt-3 mb-3"),
            
            # Metriken
            dbc.Row([
                dbc.Col(dbc.Card(dbc.CardBody([
                    html.H5("Stellenangebote gesamt"),
                    html.H3(f"{len(df)}")
                ])), width=6),
                dbc.Col(dbc.Card(dbc.CardBody([
                    html.H5("Ministerien"),
                    html.H3(f"{df['Ministry'].nunique()}")
                ])), width=6)
            ], className="mb-4"),
            
            # Einfache Diagramme
            html.H4("Angebote nach Kategorie", className="mt-3"),
            dcc.Graph(
                figure=px.bar(
                    df.groupby("Job Category").size().reset_index(name='count'),
                    x='count',
                    y="Job Category",
                    orientation='h',
                    title="Jobs nach Kategorie",
                    labels={"count": "Anzahl", "Job Category": "Kategorie"}
                )
            ),
            
            html.H4("Angebote nach Ministerium", className="mt-3"),
            dcc.Graph(
                figure=px.pie(
                    df.groupby("Ministry").size().reset_index(name='count'),
                    values='count',
                    names="Ministry",
                    title="Jobs nach Ministerium",
                    hole=0.4
                )
            )
        ]),
        
        # Stellenangebote-Tab
        dbc.Tab(label="🆕 Stellenangebote", children=[
            html.H3("Stellenangebote", className="mt-3"),
            
            # Tabelle mit Jobs
            dash_table.DataTable(
                columns=[
                    {"name": "Titel", "id": "Title"},
                    {"name": "Ministerium", "id": "Ministry"},
                    {"name": "Verwaltung", "id": "Administration/Organization"},
                    {"name": "Kategorie", "id": "Job Category"},
                    {"name": "Frist", "id": "Application Deadline"}
                ],
                data=df.to_dict('records'),
                filter_action="native",
                sort_action="native",
                sort_mode="multi",
                page_size=10,
                style_table={'overflowX': 'auto'},
                style_cell={'textAlign': 'left'}
            )
        ])
    ]),
    
    # Footer
    html.Hr(),
    html.Footer([
        html.P("Erstellt mit Dash Plotly", style={'textAlign': 'center'})
    ])
], fluid=True)

# App starten
if __name__ == "__main__":
    app.run_server(debug=True)