# Wizard AI for WordPress

Welcome to **Wizard AI**, the ultimate agentic AI assistant designed to seamlessly integrate into your WordPress and WooCommerce ecosystem. Wizard AI empowers you to automate tasks, generate content, interact with your site natively through code, and enhance both the administrator and frontend user experiences through advanced AI capabilities.

## 🚀 Main Features

### 1. 🦸‍♂️ AI Playground
The AI Playground is your dedicated command center. Built right into the WordPress dashboard, it provides a safe, highly isolated environment where you can instruct the AI to perform complex tasks.
- **Agentic Actions:** The AI can read data, execute PHP code, query your database, manage posts, and update options directly. 
- **Safe Mode:** Built-in safeguards ensure that if a third-party plugin causes a fatal error, the AI recovers autonomously in Strict Safe Mode. 
- **Rollbacks:** Every critical action taken by the AI is backed up. With a single click, you can roll back files, database changes, or options to their previous state.
- **Context Injection:** Easily pass your current WordPress environment, system info, and custom session context to the AI to give it the data it needs to succeed.

### 2. 🧠 RAG Integration (Retrieval-Augmented Generation)
Give your AI the power to truly understand your website's content. Wizard AI features a robust RAG infrastructure powered by a local SQLite vector database.
- **Automated Sync:** Schedule background cron jobs to automatically vectorize your Posts, Products, Terms, Users, and Plugins into the RAG database using Google Gemini, OpenAI, or HuggingFace embeddings.
- **Smart Context Loading:** The AI Playground features a dedicated RAG section that lets you inject specific data sets (e.g., only "Products" or only "Posts") directly into the AI's prompt. 
- **Token Optimization:** RAG data checkboxes automatically uncheck after submission to ensure large data chunks are only sent when explicitly requested, drastically saving your API token usage.

### 3. 💬 Frontend Chatbot
Bring the power of AI to your site visitors. Wizard AI includes a frontend Chatbot integration that allows your users to interact with a knowledgeable assistant directly on your pages.
- **Knowledge Base:** The Chatbot leverages your synced RAG vector embeddings to answer user questions using your real site data and product information.
- **Seamless Integration:** Easily inject the Chatbot onto your frontend pages to provide instant customer support, product recommendations, and automated guidance.

### 4. 🪄 Block Editor (Gutenberg) Wizard
Writing and editing content is easier than ever with the Wizard AI Gutenberg integration.
- **Autonomous Editing:** The AI operates directly within your Block Editor, capable of generating entirely new blocks or replacing existing ones.
- **Native Formatting:** Request the AI to write a new section, and it will output standard Gutenberg HTML, inserting the content natively and safely into your current layout.
- **Editorial Toolkit:** Instantly access AI tools to generate excerpts, meta descriptions, image alt text, summarize content, or optimize your SEO titles.

## 🛠️ Requirements & Setup
- **WordPress 6.0+**
- **PHP 8.0+** (PHP 8.2+ recommended)
- **AI API Keys**: Wizard AI uses the native WordPress Core AI Connectors. You must configure your API keys (Gemini, OpenAI, or HuggingFace) under the AI Connectors menu.

## 🛡️ Security & Privacy
Wizard AI operates with strict boundaries. It is prevented from modifying WordPress core files or its own plugin files. All database queries, file modifications, and PHP executions are presented visually with rollback states, ensuring you always have full control and visibility over what the AI is doing.
