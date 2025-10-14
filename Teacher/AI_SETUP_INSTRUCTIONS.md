# AI Question Generator Setup Instructions

This guide will help you set up the AI-powered question generation feature using ChatGPT (OpenAI GPT-4o).

## Prerequisites

1. **OpenAI API Account**: You need an OpenAI account with API access
2. **API Key**: Generate an API key from your OpenAI dashboard
3. **PHP cURL Extension**: Ensure your server has cURL enabled

## Setup Steps

### 1. Get OpenAI API Key

1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Sign up or log in to your account
3. Navigate to "API Keys" in your dashboard
4. Click "Create new secret key"
5. Copy the generated API key (starts with `sk-`)

### 2. Configure the API Key

1. Open `Teacher/config/ai_config.php`
2. Find the line: `define('OPENAI_API_KEY', 'your-openai-api-key-here');`
3. Replace `'your-openai-api-key-here'` with your actual API key:

```php
define('OPENAI_API_KEY', 'sk-your-actual-api-key-here');
```

### 3. Test the Configuration

1. Go to your material question builder page
2. Click the "Generate with AI" button
3. If configured correctly, you should see the AI generator modal

## Features

### Question Types Supported
- **Multiple Choice Questions (MCQ)**: 4 options with one correct answer
- **Matching Questions**: Connect items from two columns
- **Essay Questions**: Open-ended questions with rubrics

### Customization Options
- **Number of Questions**: 3, 5, 7, or 10 questions
- **Difficulty Level**: Easy, Medium, Hard
- **Question Types**: Select which types to generate

### AI Models Available
- **GPT-4o** (Recommended): Best quality, higher cost
- **GPT-4o-mini**: Good quality, lower cost
- **GPT-4-turbo**: Alternative option

## Cost Information

### OpenAI Pricing (as of 2024)
- **GPT-4o**: ~$0.005 per 1K input tokens, ~$0.015 per 1K output tokens
- **GPT-4o-mini**: ~$0.00015 per 1K input tokens, ~$0.0006 per 1K output tokens

### Estimated Costs
- **5 questions from a typical PDF**: ~$0.10-0.20 with GPT-4o
- **5 questions from a typical PDF**: ~$0.01-0.02 with GPT-4o-mini

## Configuration Options

### Model Selection
To change the AI model, edit `Teacher/config/ai_config.php`:

```php
define('OPENAI_MODEL', 'gpt-4o-mini'); // For lower cost
// or
define('OPENAI_MODEL', 'gpt-4o'); // For best quality
```

### Content Limits
- **Maximum content length**: 8,000 characters (configurable)
- **Minimum content length**: 100 characters
- **Maximum questions**: 10 per generation

### Rate Limiting (Optional)
Enable rate limiting to control usage:

```php
define('AI_RATE_LIMIT_ENABLED', true);
define('AI_RATE_LIMIT_REQUESTS', 10); // Max requests per hour
```

## Usage Instructions

### For Teachers

1. **Navigate to Material Question Builder**
   - Go to Content Management
   - Select a material
   - Click "Create Questions"

2. **Generate AI Questions**
   - Click "Generate with AI" button
   - Select number of questions (3-10)
   - Choose difficulty level
   - Select question types
   - Click "Generate Questions"

3. **Review and Edit**
   - Review generated questions
   - Edit question text, options, or points as needed
   - Add or remove questions manually if desired

4. **Save Questions**
   - Select target sections
   - Enter question set title
   - Click "Create Questions"

### Best Practices

1. **Content Quality**: Ensure your material has clear, educational content
2. **Review Questions**: Always review AI-generated questions before saving
3. **Edit as Needed**: Modify questions to match your specific teaching goals
4. **Mix Question Types**: Use a variety of question types for better assessment

## Troubleshooting

### Common Issues

1. **"AI features are not configured"**
   - Check that your API key is set in `ai_config.php`
   - Ensure the API key is valid and active

2. **"Failed to connect to OpenAI API"**
   - Check your internet connection
   - Verify your API key has sufficient credits
   - Check OpenAI service status

3. **"Invalid response from AI"**
   - The AI response may be malformed
   - Try generating fewer questions
   - Check the error logs in `Teacher/logs/`

4. **Questions not generating**
   - Ensure material content is substantial (>100 characters)
   - Check that at least one question type is selected
   - Verify your OpenAI account has available credits

### Error Logs

Check these files for detailed error information:
- `Teacher/logs/ai_usage.log` - AI usage tracking
- `Teacher/logs/admin_errors.log` - General error logs

## Security Notes

1. **API Key Security**
   - Never commit API keys to version control
   - Use environment variables in production
   - Regularly rotate your API keys

2. **Content Privacy**
   - Material content is sent to OpenAI for processing
   - Ensure compliance with your organization's data policies
   - Consider using OpenAI's data processing agreements

## Support

If you encounter issues:

1. Check the error logs
2. Verify your OpenAI account status
3. Test with a simple material first
4. Contact your system administrator

## Future Enhancements

Potential improvements:
- Support for more AI models (Claude, Gemini)
- Batch processing for multiple materials
- Question difficulty analysis
- Learning objective alignment
- Question bank integration
