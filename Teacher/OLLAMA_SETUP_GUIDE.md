# Ollama Setup Guide - FREE Local AI for Question Generation

Ollama allows you to run AI models locally on your computer, completely eliminating API costs! This guide will help you set up Ollama for your capstone project.

## 🎯 **Why Use Ollama?**

✅ **100% FREE** - No API costs, no monthly fees
✅ **Privacy** - Your data never leaves your computer
✅ **Offline** - Works without internet connection
✅ **Fast** - No network latency
✅ **Customizable** - Use different models as needed

## 📋 **System Requirements**

### **Minimum Requirements:**
- **RAM**: 8GB (16GB recommended)
- **Storage**: 4GB free space
- **OS**: Windows 10/11, macOS, or Linux

### **Recommended for Best Performance:**
- **RAM**: 16GB+
- **Storage**: 10GB+ free space
- **GPU**: Optional but recommended for faster processing

## 🚀 **Installation Steps**

### **Step 1: Download Ollama**

1. Go to [https://ollama.ai](https://ollama.ai)
2. Click "Download" for your operating system
3. Run the installer and follow the setup wizard

### **Step 2: Install a Model**

Open Command Prompt/Terminal and run:

```bash
# Install the recommended lightweight model (3GB)
ollama pull llama3.2:3b

# Alternative: Even lighter model (1.4GB)
ollama pull llama3.2:1b

# Alternative: Better quality model (4.7GB)
ollama pull llama3.2:8b
```

### **Step 3: Test Ollama**

```bash
# Test if Ollama is running
ollama list

# Test the model
ollama run llama3.2:3b
```

## 🔧 **Configure Your Capstone System**

### **Step 1: Update Configuration**

Edit `Teacher/config/ollama_config.php`:

```php
// Use the model you installed
define('OLLAMA_MODEL', 'llama3.2:3b'); // or llama3.2:1b, llama3.2:8b
```

### **Step 2: Test Integration**

Visit: `http://localhost/Capstone/Teacher/test_ollama_config.php`

## 📊 **Model Comparison**

| Model | Size | RAM Usage | Speed | Quality | Best For |
|-------|------|-----------|-------|---------|----------|
| **llama3.2:1b** | 1.4GB | 2GB | ⚡⚡⚡ | ⭐⭐⭐ | Quick testing |
| **llama3.2:3b** | 3GB | 4GB | ⚡⚡ | ⭐⭐⭐⭐ | **Recommended** |
| **llama3.2:8b** | 4.7GB | 8GB | ⚡ | ⭐⭐⭐⭐⭐ | Best quality |

## 🎮 **Usage Instructions**

### **For Teachers:**

1. **Start Ollama Service** (if not running automatically)
2. **Go to Material Question Builder**
3. **Click "Generate with AI (OLLAMA)"**
4. **Configure settings and generate questions**

### **Starting Ollama Service:**

**Windows:**
- Ollama should start automatically
- If not, run: `ollama serve`

**macOS/Linux:**
- Run: `ollama serve`

## 🔍 **Troubleshooting**

### **Common Issues:**

1. **"Ollama is not running"**
   ```bash
   # Start Ollama service
   ollama serve
   ```

2. **"Model not found"**
   ```bash
   # Install the model
   ollama pull llama3.2:3b
   ```

3. **"Connection failed"**
   - Check if Ollama is running on port 11434
   - Try: `http://localhost:11434/api/tags`

4. **Slow performance**
   - Use a smaller model: `llama3.2:1b`
   - Close other applications
   - Ensure sufficient RAM

### **Performance Tips:**

1. **Use SSD storage** for faster model loading
2. **Close unnecessary applications** to free RAM
3. **Start with llama3.2:1b** for testing
4. **Upgrade to llama3.2:3b** for production

## 🆚 **Ollama vs OpenAI API**

| Feature | Ollama (Local) | OpenAI API |
|---------|----------------|------------|
| **Cost** | 🆓 FREE | 💰 Pay per use |
| **Privacy** | 🔒 100% Private | 🌐 Data sent to OpenAI |
| **Internet** | ❌ Not required | ✅ Required |
| **Speed** | ⚡ Fast (local) | 🐌 Network dependent |
| **Setup** | 🔧 One-time setup | ✅ Instant |
| **Maintenance** | 🔧 Model updates | ✅ No maintenance |

## 🎯 **Recommended Setup for Your Capstone**

### **For Development/Testing:**
```bash
ollama pull llama3.2:1b  # Fast, lightweight
```

### **For Production/Demo:**
```bash
ollama pull llama3.2:3b  # Good balance
```

### **For Best Quality:**
```bash
ollama pull llama3.2:8b  # High quality (needs 8GB+ RAM)
```

## 🚀 **Advanced Configuration**

### **Custom Model Settings:**

Edit `Teacher/config/ollama_config.php`:

```php
// Adjust for your hardware
define('OLLAMA_TEMPERATURE', 0.7);  // Creativity (0.1-1.0)
define('OLLAMA_MAX_TOKENS', 4000);  // Response length
define('OLLAMA_TIMEOUT', 60);       // Timeout in seconds
```

### **Multiple Models:**

You can install multiple models and switch between them:

```bash
ollama pull llama3.2:1b
ollama pull llama3.2:3b
ollama pull mistral:7b
```

## 📈 **Performance Monitoring**

Check Ollama usage logs:
- `Teacher/logs/ollama_usage.log`

Monitor system resources:
- RAM usage should be 2-8GB depending on model
- CPU usage during generation

## 🎉 **Benefits for Your Capstone**

1. **Zero Cost** - Perfect for student projects
2. **Privacy** - Educational data stays local
3. **Reliability** - No API rate limits or downtime
4. **Learning** - Understand how AI models work
5. **Portfolio** - Shows advanced technical skills

## 🔗 **Useful Commands**

```bash
# List installed models
ollama list

# Remove a model
ollama rm llama3.2:1b

# Update Ollama
ollama update

# Check Ollama status
curl http://localhost:11434/api/tags
```

## 📞 **Support**

If you encounter issues:

1. Check the [Ollama Documentation](https://ollama.ai/docs)
2. Visit [Ollama GitHub](https://github.com/ollama/ollama)
3. Check system requirements
4. Try a smaller model first

---

**🎯 Recommendation: Start with `llama3.2:3b` - it's the perfect balance of quality, speed, and resource usage for your capstone project!**
