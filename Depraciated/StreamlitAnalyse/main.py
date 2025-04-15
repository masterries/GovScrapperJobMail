import streamlit as st
import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
import requests
import re
from datetime import datetime, timedelta
from collections import defaultdict

# Page config
st.set_page_config(
    page_title="Luxembourg Government Jobs Dashboard",
    page_icon="🇱🇺",
    layout="wide"
)

# Title and description
st.title("🇱🇺 Luxembourg Government Jobs Analysis")
st.markdown("""
This dashboard analyzes job listings from the Luxembourg government portal and identifies unique job postings,
potential duplicate listings, and hot jobs (positions that are repeatedly posted after a month).
""")

# Simplified function to extract base job title - just remove everything in parentheses
def extract_base_title(title):
    if pd.isna(title):
        return ""
    # Convert to string in case it's not
    title = str(title)
    # Remove everything in parentheses
    base_title = re.sub(r'\([^)]*\)', '', title)
    # Remove "- Recrutement interne" and similar suffixes
    base_title = re.sub(r'-\s*Recrutement\s*interne', '', base_title, flags=re.IGNORECASE)
    # Clean up extra spaces
    base_title = re.sub(r'\s+', ' ', base_title).strip()
    return base_title

# Function to find jobs that appear to be the same position but posted with larger time gaps
def find_hot_jobs(df, min_time_window_days=30):
    if "adding_date" not in df.columns or "Base Title" not in df.columns:
        return []
    
    # Create a dictionary to group jobs by base title
    title_to_jobs = defaultdict(list)
    
    for idx, row in df.iterrows():
        if pd.notna(row['adding_date']) and pd.notna(row['Base Title']) and row['Base Title'] != "":
            title_to_jobs[row['Base Title']].append({
                'index': idx,
                'adding_date': row['adding_date'],
                'title': row['Title']
            })
    
    # Find hot jobs by looking for same base titles posted at least 30 days apart
    hot_job_groups = []
    
    for base_title, jobs in title_to_jobs.items():
        if len(jobs) > 1:  # At least 2 postings with the same base title
            # Sort jobs by date
            jobs.sort(key=lambda x: x['adding_date'])
            
            # Check if any postings are at least 30 days apart
            is_hot = False
            for i in range(1, len(jobs)):
                time_gap = (jobs[i]['adding_date'] - jobs[i-1]['adding_date']).days
                if time_gap >= min_time_window_days:
                    is_hot = True
                    break
            
            if is_hot:
                hot_job_groups.append(jobs)
    
    return hot_job_groups

# Function to find similar job listings and group them - simplified for speed
def find_similar_jobs(df, time_window_days=1):
    similar_job_groups = []
    processed_indices = set()
    
    # Create a copy with index for reference
    df_with_index = df.copy()
    df_with_index['original_index'] = df_with_index.index
    
    # Create a dictionary to group jobs by base title and date
    title_date_to_jobs = defaultdict(list)
    
    if 'adding_date' in df.columns:
        for i, row in df_with_index.iterrows():
            if pd.notna(row['adding_date']) and pd.notna(row['Base Title']) and row['Base Title'] != "":
                # Use base title and date as key
                date_key = row['adding_date'].date()
                title_key = row['Base Title']
                combined_key = (title_key, date_key)
                
                title_date_to_jobs[combined_key].append(i)
                
                # Also check the next day
                next_day = date_key + timedelta(days=1)
                next_day_key = (title_key, next_day)
                title_date_to_jobs[next_day_key].append(i)
                
                # And the previous day
                prev_day = date_key - timedelta(days=1)
                prev_day_key = (title_key, prev_day)
                title_date_to_jobs[prev_day_key].append(i)
    
    # Find groups of similar jobs
    for combined_key, indices in title_date_to_jobs.items():
        if len(indices) > 1:  # At least 2 jobs with same base title on same/adjacent days
            # Filter out already processed indices
            group = [idx for idx in indices if idx not in processed_indices]
            
            if len(group) > 1:
                # Mark these indices as processed
                for idx in group:
                    processed_indices.add(idx)
                
                # Get the original indices
                original_indices = [df_with_index.loc[idx, 'original_index'] for idx in group]
                similar_job_groups.append(original_indices)
    
    return similar_job_groups

# Function to create a sankey diagram showing relationships between ministries, administrations, and job categories
def create_relationship_sankey(df, max_items=15):
    # Prepare the data for the Sankey diagram
    df_clean = df.copy()
    
    # Fill NaN values with "Unknown" for the diagram
    for col in ['Ministry', 'Administration/Organization', 'Job Category']:
        if col in df_clean.columns:
            df_clean[col] = df_clean[col].fillna('Unknown')
    
    # Get the top values for each category to avoid overcrowding
    top_ministries = df_clean['Ministry'].value_counts().nlargest(max_items).index.tolist()
    top_admins = df_clean['Administration/Organization'].value_counts().nlargest(max_items).index.tolist()
    top_categories = df_clean['Job Category'].value_counts().nlargest(max_items).index.tolist()
    
    # Filter dataframe to only include top values
    df_filtered = df_clean[
        (df_clean['Ministry'].isin(top_ministries)) & 
        (df_clean['Administration/Organization'].isin(top_admins)) & 
        (df_clean['Job Category'].isin(top_categories))
    ]
    
    # Create nodes list
    ministry_nodes = [{'name': ministry} for ministry in top_ministries]
    admin_nodes = [{'name': admin} for admin in top_admins]
    category_nodes = [{'name': category} for category in top_categories]
    
    all_nodes = ministry_nodes + admin_nodes + category_nodes
    
    # Create a lookup dictionary for node indices
    node_indices = {node['name']: i for i, node in enumerate(all_nodes)}
    
    # Create links from Ministry to Administration
    ministry_admin_links = df_filtered.groupby(['Ministry', 'Administration/Organization']).size().reset_index(name='value')
    ministry_admin_links = ministry_admin_links[ministry_admin_links['value'] > 0]  # Filter out zero values
    
    # Create links from Administration to Job Category
    admin_category_links = df_filtered.groupby(['Administration/Organization', 'Job Category']).size().reset_index(name='value')
    admin_category_links = admin_category_links[admin_category_links['value'] > 0]  # Filter out zero values
    
    # Create the links data
    links = []
    
    # Add ministry to admin links
    for _, row in ministry_admin_links.iterrows():
        source_idx = node_indices.get(row['Ministry'])
        target_idx = node_indices.get(row['Administration/Organization'])
        if source_idx is not None and target_idx is not None:
            links.append({
                'source': source_idx,
                'target': target_idx,
                'value': row['value']
            })
    
    # Add admin to category links
    for _, row in admin_category_links.iterrows():
        source_idx = node_indices.get(row['Administration/Organization'])
        target_idx = node_indices.get(row['Job Category'])
        if source_idx is not None and target_idx is not None:
            links.append({
                'source': source_idx,
                'target': target_idx,
                'value': row['value']
            })
    
    # Create the Sankey diagram
    fig = go.Figure(data=[go.Sankey(
        node = dict(
            pad = 15,
            thickness = 20,
            line = dict(color = "black", width = 0.5),
            label = [node['name'] for node in all_nodes],
            color = ["blue"] * len(ministry_nodes) + ["green"] * len(admin_nodes) + ["red"] * len(category_nodes)
        ),
        link = dict(
            source = [link['source'] for link in links],
            target = [link['target'] for link in links],
            value = [link['value'] for link in links]
        )
    )])
    
    fig.update_layout(
        title_text="Relationships between Ministries, Administrations, and Job Categories",
        font_size=10,
        height=800
    )
    
    return fig

# Function to process job listings and identify unique vs. duplicate listings and hot jobs
def process_job_listings(df):
    # Find similar job groups that were posted within 1 day (duplicates)
    similar_job_groups = find_similar_jobs(df, time_window_days=1)
    
    # Add a column to indicate if a job is part of a similar group
    df['is_duplicate'] = False
    df['group_id'] = -1
    
    for group_id, group in enumerate(similar_job_groups):
        # Sort group by date if available, otherwise keep as is
        if 'adding_date' in df.columns:
            sorted_group = sorted(group, key=lambda x: df.loc[x, 'adding_date'] if not pd.isna(df.loc[x, 'adding_date']) else pd.Timestamp('2099-01-01'))
        else:
            sorted_group = group
        
        # Mark first job as original, rest as duplicates
        first_job = sorted_group[0]
        duplicate_jobs = sorted_group[1:]
        
        df.loc[duplicate_jobs, 'is_duplicate'] = True
        df.loc[group, 'group_id'] = group_id
    
    # Calculate total unique jobs (not part of any group, or first in a group)
    unique_jobs = df[~df['is_duplicate']].copy()
    
    # Find hot jobs - jobs that are posted again after a month
    hot_job_groups = find_hot_jobs(df, min_time_window_days=30)
    
    # Create list of indices from all hot jobs
    hot_jobs_indices = []
    for group in hot_job_groups:
        for job in group:
            hot_jobs_indices.append(job['index'])
    
    # Create DataFrame with hot job information if any indices were found
    if hot_jobs_indices:
        hot_jobs = df.loc[hot_jobs_indices].copy()
        hot_jobs['is_hot_job'] = True
    else:
        hot_jobs = pd.DataFrame()
    
    return df, unique_jobs, similar_job_groups, hot_jobs, hot_job_groups

# Load and preprocess data, cache for 1 hour
@st.cache_data(ttl=3600)
def load_and_preprocess_data():
    try:
        # Load from GitHub repository
        url = "https://raw.githubusercontent.com/masterries/GovScrapperJobMail/main/jobs_all_processed.json"
        response = requests.get(url)
        data = response.json()
        df = pd.DataFrame(data)
        
        # Clean up date columns
        if "adding_date" in df.columns:
            df["adding_date"] = pd.to_datetime(df["adding_date"])
        
        if "Application Deadline" in df.columns:
            df["Application Deadline"] = pd.to_datetime(df["Application Deadline"], format="%d/%m/%Y", errors="coerce")
        
        # Extract base title - simplify for speed
        df['Base Title'] = df['Title'].apply(extract_base_title)
        
        # Process to identify duplicates and hot jobs
        df, unique_jobs, similar_job_groups, hot_jobs, hot_job_groups = process_job_listings(df)
        
        return df, unique_jobs, similar_job_groups, hot_jobs, hot_job_groups
    except Exception as e:
        st.error(f"Error loading data: {e}")
        return pd.DataFrame(), pd.DataFrame(), [], pd.DataFrame(), []

# Load the data
df, unique_jobs, similar_job_groups, hot_jobs, hot_job_groups = load_and_preprocess_data()

if df.empty:
    st.error("Failed to load data. Please check the data source.")
    st.stop()

# Display data loading success
st.success(f"Successfully loaded {len(df)} job listings ({len(unique_jobs)} unique jobs, {len(df) - len(unique_jobs)} duplicates)")

# Create tabs for different views
tab1, tab2, tab3, tab4, tab5 = st.tabs(["📊 Overview", "🔥 Hot Jobs", "📈 Analysis & Correlations", "🆕 Unique Jobs", "🔍 Similar Listings"])

with tab1:
    st.header("Overview")
    
    # Overview metrics in two rows
    col1, col2, col3 = st.columns(3)
    
    with col1:
        st.metric("Total Job Listings", len(df))
    
    with col2:
        st.metric("Unique Job Positions", len(unique_jobs))
    
    with col3:
        st.metric("Duplicate Listings", len(df) - len(unique_jobs))
    
    col1, col2, col3 = st.columns(3)
    
    with col1:
        st.metric("Hot Jobs (Reposted after 30+ days)", len(hot_job_groups))
    
    with col2:
        if "Administration/Organization" in df.columns:
            st.metric("Administrations", df["Administration/Organization"].dropna().nunique())
    
    with col3:
        if "Job Category" in df.columns:
            st.metric("Job Categories", df["Job Category"].dropna().nunique())
    
    # Bar chart of unique vs duplicate
    duplicate_counts = df['is_duplicate'].value_counts().reset_index()
    duplicate_counts.columns = ['Is Duplicate', 'Count']
    duplicate_counts['Is Duplicate'] = duplicate_counts['Is Duplicate'].map({True: 'Duplicate Listing', False: 'Unique Job'})
    
    fig = px.bar(
        duplicate_counts,
        x='Is Duplicate',
        y='Count',
        title="Distribution of Unique Jobs vs Duplicate Listings",
        color='Is Duplicate',
        color_discrete_map={'Unique Job': '#2ca02c', 'Duplicate Listing': '#d62728'}
    )
    st.plotly_chart(fig, use_container_width=True)
    
    # Show job distribution over time if we have dates
    if "adding_date" in df.columns:
        st.subheader("Job Listings Over Time")
        
        # Create comparison of all jobs vs unique jobs
        df_all = df.copy()
        df_unique = unique_jobs.copy()
        
        # Filter out rows with missing dates
        df_all = df_all.dropna(subset=["adding_date"])
        df_unique = df_unique.dropna(subset=["adding_date"])
        
        if not df_all.empty and not df_unique.empty:
            # Group by date and count
            df_all['date'] = df_all['adding_date'].dt.date
            all_daily_counts = df_all.groupby('date').size().reset_index(name='count')
            all_daily_counts['type'] = 'All Listings'
            
            df_unique['date'] = df_unique['adding_date'].dt.date
            unique_daily_counts = df_unique.groupby('date').size().reset_index(name='count')
            unique_daily_counts['type'] = 'Unique Positions'
            
            # Combine datasets
            combined_counts = pd.concat([all_daily_counts, unique_daily_counts])
            
            fig = px.line(
                combined_counts,
                x="date",
                y="count", 
                color="type",
                title="Job Postings Over Time: All vs Unique",
                labels={"date": "Date", "count": "Number of Jobs"},
                markers=True,
                color_discrete_map={'All Listings': '#1f77b4', 'Unique Positions': '#2ca02c'}
            )
            st.plotly_chart(fig, use_container_width=True)
        else:
            st.info("No date information available to show timeline.")

with tab2:
    st.header("🔥 Hot Jobs Analysis")
    
    st.markdown("""
    This tab focuses on "hot jobs" - positions that are reposted after at least 30 days.
    These are likely positions that are difficult to fill or have high turnover.
    """)
    
    if not hot_job_groups:
        st.warning("No hot jobs identified. This could be due to missing date information or no jobs reposted after 30+ days.")
    else:
        # Overview metrics for hot jobs
        st.metric("Total Hot Jobs (Reposted Positions)", len(hot_job_groups))
        
        # Hot jobs by category and ministry
        if not hot_jobs.empty:
            col1, col2 = st.columns(2)
            
            with col1:
                # Hot jobs by category
                if "Job Category" in hot_jobs.columns:
                    st.subheader("Hot Jobs by Category")
                    category_counts = hot_jobs["Job Category"].fillna("Unknown").value_counts().reset_index()
                    category_counts.columns = ["Category", "Count"]
                    
                    # If too many categories, show only top 10
                    if len(category_counts) > 10:
                        category_counts = category_counts.head(10)
                    
                    fig = px.bar(
                        category_counts,
                        x="Count",
                        y="Category",
                        orientation="h",
                        title="Top Job Categories That Need Reposting",
                        color_discrete_sequence=["#ff7f0e"]
                    )
                    st.plotly_chart(fig, use_container_width=True)
            
            with col2:
                # Hot jobs by ministry
                if "Ministry" in hot_jobs.columns:
                    st.subheader("Hot Jobs by Ministry")
                    ministry_counts = hot_jobs["Ministry"].fillna("Unknown").value_counts().reset_index()
                    ministry_counts.columns = ["Ministry", "Count"]
                    
                    # If too many ministries, show only top 10
                    if len(ministry_counts) > 10:
                        ministry_counts = ministry_counts.head(10)
                    
                    fig = px.bar(
                        ministry_counts,
                        x="Count",
                        y="Ministry",
                        orientation="h",
                        title="Top Ministries with Reposted Positions",
                        color_discrete_sequence=["#d62728"]
                    )
                    st.plotly_chart(fig, use_container_width=True)
        
        # List each hot job group
        st.subheader("Repeatedly Posted Job Positions")
        
        for i, group in enumerate(hot_job_groups):
            # Get the base title from the first job in the group
            base_title = df.loc[group[0]['index'], 'Base Title'] if 'Base Title' in df.columns else f"Hot Job Group {i+1}"
            
            with st.expander(f"{base_title} ({len(group)} postings)"):
                # Create a DataFrame for this group
                group_data = []
                for job in group:
                    job_row = df.loc[job['index']]
                    group_data.append({
                        'Title': job['title'],
                        'Base Title': job_row['Base Title'] if 'Base Title' in job_row else "",
                        'Posted Date': job['adding_date'],
                        'Ministry': job_row['Ministry'] if 'Ministry' in job_row else "Unknown",
                        'Administration': job_row['Administration/Organization'] if 'Administration/Organization' in job_row else "Unknown",
                        'Index': job['index']
                    })
                
                group_df = pd.DataFrame(group_data)
                group_df = group_df.sort_values(by='Posted Date')
                
                # Calculate time between postings
                if len(group_df) > 1:
                    first_date = group_df.iloc[0]['Posted Date']
                    group_df['Days Since First Posting'] = (group_df['Posted Date'] - first_date).dt.days
                    
                    # Calculate days between consecutive postings
                    posting_dates = group_df['Posted Date'].tolist()
                    days_between = []
                    days_between.append(0)  # First posting has no previous
                    
                    for i in range(1, len(posting_dates)):
                        days = (posting_dates[i] - posting_dates[i-1]).days
                        days_between.append(days)
                    
                    group_df['Days Since Previous Posting'] = days_between
                
                # Format date for display
                group_df['Posted Date'] = group_df['Posted Date'].dt.strftime('%Y-%m-%d')
                
                # Display the group data
                st.dataframe(group_df)
                
                # Show visualization of postings over time
                if len(group_df) > 1:
                    # Convert back to datetime for plotting
                    group_df['Posted Date'] = pd.to_datetime(group_df['Posted Date'])
                    
                    # Create timeline visualization
                    fig = px.scatter(
                        group_df,
                        x='Posted Date',
                        y=[base_title] * len(group_df),
                        size='Days Since Previous Posting',
                        size_max=20,
                        title=f"Timeline of Postings for: {base_title}",
                        labels={'y': 'Job', 'Posted Date': 'Date Posted'},
                    )
                    
                    # Add a line connecting the points
                    fig.add_scatter(
                        x=group_df['Posted Date'],
                        y=[base_title] * len(group_df),
                        mode='lines',
                        line=dict(color='rgba(0,0,0,0.3)'),
                        showlegend=False
                    )
                    
                    fig.update_layout(
                        showlegend=False,
                        yaxis_title=None,
                        yaxis_showticklabels=False
                    )
                    
                    st.plotly_chart(fig, use_container_width=True)
                
                # Show details of the latest job posting
                latest_job = group_df.iloc[-1]
                latest_job_details = df.loc[latest_job['Index']]
                
                st.markdown("### Latest Posting Details")
                
                col1, col2 = st.columns(2)
                
                with col1:
                    st.markdown("#### Organization Details")
                    if "Ministry" in latest_job_details and not pd.isna(latest_job_details["Ministry"]):
                        st.markdown(f"**Ministry:** {latest_job_details['Ministry']}")
                    if "Administration/Organization" in latest_job_details and not pd.isna(latest_job_details["Administration/Organization"]):
                        st.markdown(f"**Administration:** {latest_job_details['Administration/Organization']}")
                    if "Status" in latest_job_details and not pd.isna(latest_job_details["Status"]):
                        st.markdown(f"**Status:** {latest_job_details['Status']}")
                    if "Task" in latest_job_details and not pd.isna(latest_job_details["Task"]):
                        st.markdown(f"**Task:** {latest_job_details['Task']}")
                
                with col2:
                    st.markdown("#### Job Requirements")
                    if "Education Level" in latest_job_details and not pd.isna(latest_job_details["Education Level"]):
                        st.markdown(f"**Education:** {latest_job_details['Education Level']}")
                    if "Job Category" in latest_job_details and not pd.isna(latest_job_details["Job Category"]):
                        st.markdown(f"**Category:** {latest_job_details['Job Category']}")
                    if "Group Classification" in latest_job_details and not pd.isna(latest_job_details["Group Classification"]):
                        st.markdown(f"**Group:** {latest_job_details['Group Classification']}")
                
                # Link to application
                if "Link" in latest_job_details and pd.notna(latest_job_details["Link"]):
                    st.markdown("---")
                    st.markdown(f"[Apply for this position]({latest_job_details['Link']})")

with tab3:
    st.header("Analysis & Correlations")
    
    # Create columns for the first row of charts
    col1, col2 = st.columns(2)
    
    with col1:
        # Jobs by category (using unique jobs)
        if "Job Category" in unique_jobs.columns:
            st.subheader("Unique Jobs by Category")
            category_column = unique_jobs["Job Category"].fillna("Unknown")
            category_counts = category_column.value_counts().reset_index()
            category_counts.columns = ["Category", "Count"]
            
            # If too many categories, show only top 10
            if len(category_counts) > 10:
                category_counts = category_counts.head(10)
                title = "Top 10 Job Categories (Unique Positions)"
            else:
                title = "Job Categories (Unique Positions)"
            
            fig = px.bar(
                category_counts,
                x="Count",
                y="Category",
                orientation="h",
                title=title,
                color_discrete_sequence=["#1f77b4"]
            )
            st.plotly_chart(fig, use_container_width=True)
    
    with col2:
        # Jobs by ministry
        if "Ministry" in unique_jobs.columns:
            st.subheader("Unique Jobs by Ministry")
            ministry_column = unique_jobs["Ministry"].fillna("Unknown")
            ministry_counts = ministry_column.value_counts().reset_index()
            ministry_counts.columns = ["Ministry", "Count"]
            
            # If too many ministries, show only top 10
            if len(ministry_counts) > 10:
                ministry_counts = ministry_counts.head(10)
                title = "Top 10 Ministries (Unique Positions)"
            else:
                title = "Ministries (Unique Positions)"
            
            fig = px.bar(
                ministry_counts,
                x="Count",
                y="Ministry",
                orientation="h",
                title=title,
                color_discrete_sequence=["#ff7f0e"]
            )
            st.plotly_chart(fig, use_container_width=True)
    
    # New chart for Administrations
    if "Administration/Organization" in unique_jobs.columns:
        st.subheader("Jobs by Administration/Organization")
        admin_column = unique_jobs["Administration/Organization"].fillna("Unknown")
        admin_counts = admin_column.value_counts().reset_index()
        admin_counts.columns = ["Administration", "Count"]
        
        # Show only top 15 administrations since there could be many
        if len(admin_counts) > 15:
            admin_counts = admin_counts.head(15)
            title = "Top 15 Administrations/Organizations (Unique Positions)"
        else:
            title = "Administrations/Organizations (Unique Positions)"
        
        fig = px.bar(
            admin_counts,
            x="Count",
            y="Administration",
            orientation="h",
            title=title,
            color_discrete_sequence=["#2ca02c"]
        )
        st.plotly_chart(fig, use_container_width=True)
    
    # Sankey diagram showing relationships
    st.subheader("Relationships between Ministries, Administrations and Job Categories")
    
    # Add a note about the visualization
    st.markdown("""
    This Sankey diagram shows the flow from Ministries to Administrations to Job Categories.
    The width of each connection represents the number of job listings flowing through that path.
    Only the top entities in each category are shown to maintain readability.
    """)
    
    # Check if we have all necessary columns
    if all(col in unique_jobs.columns for col in ['Ministry', 'Administration/Organization', 'Job Category']):
        # Create the sankey diagram
        sankey_fig = create_relationship_sankey(unique_jobs)
        st.plotly_chart(sankey_fig, use_container_width=True)
    else:
        st.info("Cannot create relationship diagram. One or more required columns are missing.")
    
    # Additional correlations - heatmap for Education Level vs Job Category
    if "Education Level" in unique_jobs.columns and "Job Category" in unique_jobs.columns:
        st.subheader("Education Level vs Job Category")
        
        # Create a crosstab for the heatmap
        edu_job_crosstab = pd.crosstab(
            unique_jobs["Education Level"].fillna("Unknown"),
            unique_jobs["Job Category"].fillna("Unknown")
        )
        
        # Limit to top categories if there are too many
        if edu_job_crosstab.shape[1] > 10:
            top_categories = unique_jobs["Job Category"].value_counts().nlargest(10).index
            edu_job_crosstab = edu_job_crosstab[edu_job_crosstab.columns.intersection(top_categories)]
        
        # Create the heatmap
        fig = px.imshow(
            edu_job_crosstab,
            labels=dict(x="Job Category", y="Education Level", color="Count"),
            x=edu_job_crosstab.columns,
            y=edu_job_crosstab.index,
            color_continuous_scale="Viridis",
            title="Number of Jobs by Education Level and Job Category"
        )
        
        fig.update_layout(height=600)
        st.plotly_chart(fig, use_container_width=True)

with tab4:
    st.header("Unique Job Postings")
    st.markdown("""
    This tab shows only the unique job postings, filtering out duplicate listings of the same position.
    When multiple similar job listings were found, only the first one is shown here.
    """)
    
    # Search and filters for unique jobs
    col1, col2, col3 = st.columns(3)
    
    with col1:
        search_term = st.text_input("Search job titles", key="unique_search")
    
    with col2:
        if "Ministry" in unique_jobs.columns:
            ministry_values = [str(x) for x in unique_jobs["Ministry"].dropna().unique().tolist()]
            all_ministries = ["All Ministries"] + sorted(ministry_values)
            selected_ministry = st.selectbox("Ministry", all_ministries, key="unique_ministry")
    
    with col3:
        if "Job Category" in unique_jobs.columns:
            category_values = [str(x) for x in unique_jobs["Job Category"].dropna().unique().tolist()]
            all_categories = ["All Categories"] + sorted(category_values)
            selected_category = st.selectbox("Job Category", all_categories, key="unique_category")
    
    # Apply filters
    filtered_unique = unique_jobs.copy()
    
    if search_term:
        filtered_unique = filtered_unique[filtered_unique["Title"].fillna("").str.contains(search_term, case=False)]
    
    if "Ministry" in unique_jobs.columns and selected_ministry != "All Ministries":
        filtered_unique = filtered_unique[filtered_unique["Ministry"].fillna("").astype(str) == selected_ministry]
    
    if "Job Category" in unique_jobs.columns and selected_category != "All Categories":
        filtered_unique = filtered_unique[filtered_unique["Job Category"].fillna("").astype(str) == selected_category]
    
    # Display unique jobs
    st.write(f"Showing {len(filtered_unique)} unique job positions")
    
    # Prepare columns to display
    display_columns = ["Title", "Ministry", "Administration/Organization", 
                      "Job Category", "Education Level", "Application Deadline"]
    
    # Ensure all columns exist
    display_columns = [col for col in display_columns if col in filtered_unique.columns]
    
    # Format and display dataframe
    if not filtered_unique.empty:
        sort_col = "Application Deadline" if "Application Deadline" in filtered_unique.columns else "Title"
        ascending = False if sort_col == "Application Deadline" else True
        
        filtered_unique_sorted = filtered_unique.sort_values(
            by=sort_col,
            ascending=ascending,
            na_position='last'
        )
            
        st.dataframe(
            filtered_unique_sorted[display_columns],
            use_container_width=True,
            height=400
        )
    else:
        st.info("No unique jobs found matching your criteria.")
    
    # Job details section
    if not filtered_unique.empty:
        st.subheader("Job Details")
        
        job_titles = filtered_unique["Title"].fillna("Untitled Job").tolist()
        selected_job = st.selectbox("Select a job to view details", job_titles, key="unique_job_select")
        
        if selected_job:
            job_details = filtered_unique[filtered_unique["Title"] == selected_job].iloc[0]
            
            st.markdown(f"### {selected_job}")
            
            col1, col2 = st.columns(2)
            
            with col1:
                st.markdown("#### Organization Details")
                if "Ministry" in job_details and not pd.isna(job_details["Ministry"]):
                    st.markdown(f"**Ministry:** {job_details['Ministry']}")
                if "Administration/Organization" in job_details and not pd.isna(job_details["Administration/Organization"]):
                    st.markdown(f"**Administration:** {job_details['Administration/Organization']}")
                if "Status" in job_details and not pd.isna(job_details["Status"]):
                    st.markdown(f"**Status:** {job_details['Status']}")
                if "Task" in job_details and not pd.isna(job_details["Task"]):
                    st.markdown(f"**Task:** {job_details['Task']}")
            
            with col2:
                st.markdown("#### Job Requirements")
                if "Education Level" in job_details and not pd.isna(job_details["Education Level"]):
                    st.markdown(f"**Education:** {job_details['Education Level']}")
                if "Job Category" in job_details and not pd.isna(job_details["Job Category"]):
                    st.markdown(f"**Category:** {job_details['Job Category']}")
                if "Group Classification" in job_details and not pd.isna(job_details["Group Classification"]):
                    st.markdown(f"**Group:** {job_details['Group Classification']}")
                if "Application Deadline" in job_details and not pd.isna(job_details["Application Deadline"]):
                    deadline = job_details["Application Deadline"]
                    deadline_str = deadline.strftime("%d/%m/%Y") if pd.notna(deadline) else "Not specified"
                    st.markdown(f"**Deadline:** {deadline_str}")
            
            # Link to application
            if "Link" in job_details and pd.notna(job_details["Link"]):
                st.markdown("---")
                st.markdown(f"[Apply for this position]({job_details['Link']})")
                
            # Check if this job has duplicates
            if job_details['group_id'] >= 0:
                group_id = job_details['group_id']
                duplicate_jobs = df[(df['group_id'] == group_id) & (df['is_duplicate'] == True)]
                
                if not duplicate_jobs.empty:
                    st.markdown("---")
                    st.markdown(f"#### Related Listings ({len(duplicate_jobs)} duplicate postings)")
                    
                    # Display duplicates in a more compact form
                    for idx, dup_job in duplicate_jobs.iterrows():
                        st.markdown(f"- **{dup_job['Title']}**")
                        if 'adding_date' in duplicate_jobs.columns and not pd.isna(dup_job['adding_date']):
                            st.markdown(f"  *Posted on: {dup_job['adding_date'].strftime('%Y-%m-%d')}*")

with tab5:
    st.header("Similar Job Listings Analysis")
    
    st.markdown("""
    This analysis identifies similar job postings that might be the same position but posted differently.
    Job listings are considered similar if they have the same base title (after removing content in parentheses)
    and were posted within 1 day of each other.
    """)
    
    if not similar_job_groups:
        st.info("No similar job postings found in the dataset.")
    else:
        st.success(f"Found {len(similar_job_groups)} groups of similar job postings")
        
        # Summary visualization
        group_sizes = [len(group) for group in similar_job_groups]
        size_counts = pd.Series(group_sizes).value_counts().sort_index().reset_index()
        size_counts.columns = ['Group Size', 'Number of Groups']
        
        fig = px.bar(
            size_counts,
            x='Group Size',
            y='Number of Groups',
            title="Distribution of Similar Job Group Sizes",
            labels={'Group Size': 'Number of Similar Jobs in Group', 'Number of Groups': 'Count'},
            color_discrete_sequence=["#8884d8"]
        )
        st.plotly_chart(fig, use_container_width=True)
        
        # Show groups in expandable sections
        for i, group in enumerate(similar_job_groups):
            # Get the base title for this group
            base_title = df.loc[group[0], 'Base Title'] if len(group) > 0 and 'Base Title' in df.columns else f"Group {i+1}"
            
            with st.expander(f"{base_title} ({len(group)} postings)"):
                similar_jobs_df = df.loc[group].copy()
                
                # Add simple comparison column showing the base title
                similar_jobs_df['Base Title'] = similar_jobs_df['Base Title'].astype(str)
                
                # Format dates for display
                if 'adding_date' in similar_jobs_df.columns:
                    similar_jobs_df['adding_date'] = similar_jobs_df['adding_date'].dt.strftime('%Y-%m-%d')
                if 'Application Deadline' in similar_jobs_df.columns:
                    similar_jobs_df['Application Deadline'] = similar_jobs_df['Application Deadline'].dt.strftime('%Y-%m-%d')
                
                # Select columns to display
                display_cols = ['Title', 'Base Title', 'Ministry']
                if 'adding_date' in similar_jobs_df.columns:
                    display_cols.insert(3, 'adding_date')
                
                # Ensure all columns exist in the DataFrame
                display_cols = [col for col in display_cols if col in similar_jobs_df.columns]
                
                st.dataframe(
                    similar_jobs_df[display_cols],
                    use_container_width=True
                )
                
                # Add simple title comparison
                st.markdown("#### Job Titles")
                for idx, row in similar_jobs_df.iterrows():
                    st.markdown(f"- **{row['Title']}**")
                    if 'adding_date' in similar_jobs_df.columns and not pd.isna(row['adding_date']):
                        st.markdown(f"  *Posted on: {row['adding_date']}*")

# Add footer
st.markdown("---")
st.markdown("""
<div style="text-align: center">
    <p>Data source: <a href="https://github.com/masterries/GovScrapperJobMail">GovScrapperJobMail GitHub repository</a></p>
</div>
""", unsafe_allow_html=True)