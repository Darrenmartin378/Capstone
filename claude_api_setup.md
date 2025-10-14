# Claude AI API Setup for CompreLearn

## Overview
The CompreLearn platform has been updated to use Claude AI (Anthropic) instead of OpenAI GPT for question generation. This provides better reasoning capabilities and more sophisticated educational content generation.

## API Key Configuration

### Option 1: Environment Variable (Recommended)
Set the `CLAUDE_API_KEY` environment variable:

**Windows:**
```cmd
set CLAUDE_API_KEY=your_claude_api_key_here
```

**Linux/Mac:**
```bash
export CLAUDE_API_KEY=your_claude_api_key_here
```

### Option 2: Direct Configuration
You can also modify the code to set the API key directly in the PHP file, though this is less secure.

## Getting Your Claude API Key

1. Visit [Anthropic's Console](https://console.anthropic.com/)
2. Sign up or log in to your account
3. Navigate to the API Keys section
4. Create a new API key
5. Copy the key and set it as described above

## Features

### Enhanced AI Capabilities
- **Claude 3.5 Sonnet**: Advanced reasoning and generation quality
- **Content-Aware Analysis**: Deep understanding of educational materials
- **Sophisticated Question Generation**: Multiple choice, matching, and essay questions
- **Anti-Placeholder System**: Prevents generic or placeholder content
- **Quality Validation**: Comprehensive validation for high-quality questions

### Question Types Supported
- **Multiple Choice Questions (MCQ)**: 6-7 sophisticated questions with 4 options each
- **Matching Questions**: 4-5 content-based matching pairs
- **Essay Questions**: 4-5 analytical and critical thinking questions

### Content Analysis
- **File Support**: PDF, Word, PowerPoint, and text files
- **Multi-Material Selection**: Generate questions from multiple reading materials
- **Comprehensive Extraction**: Headings, paragraphs, lists, definitions, and examples
- **Adaptive Learning**: Learns from successful question patterns

## Troubleshooting

### Common Issues
1. **API Key Not Found**: Ensure the `CLAUDE_API_KEY` environment variable is set correctly
2. **Rate Limiting**: Claude API has rate limits; the system will fall back to local generation if needed
3. **Content Analysis**: If file content cannot be extracted, the system provides fallback content

### Fallback System
If Claude AI is unavailable or the API key is not configured, the system automatically falls back to a sophisticated local question generator that still provides high-quality educational content.

## Support
For technical support or questions about the Claude AI integration, please refer to the Anthropic documentation or contact your system administrator.
