# AI Product & Traceability Risk Analysis

## Overview

The AI Product & Traceability Risk Analysis feature provides automated analysis of product data to identify potential risks, missing information, and traceability weaknesses in the NutriTrace system.

This feature uses **Cohere AI** to generate intelligent recommendations based on deterministic data quality checks, helping producers and transformers improve the completeness and reliability of their product traceability records.

## Key Features

- **Automated Risk Detection**: Identifies missing data, inconsistencies, and potential quality issues
- **AI-Powered Recommendations**: Uses Cohere's Chat API to provide contextual, actionable recommendations
- **Four Risk Categories**:
  1. Traceability Completeness
  2. Data Consistency
  3. Quality and Storage Concerns
  4. Certification Verification
- **Transparent Scoring**: Clear risk levels (Very Low, Low, Medium, High) with detailed explanations
- **Graceful Degradation**: Works with or without AI API configuration

## Architecture

### Components

1. **CohereClient** (`app/Services/Ai/CohereClient.php`)
   - HTTP client for Cohere AI API
   - Handles authentication, retries, and error handling
   - Returns structured responses or null on failure

2. **ProductRiskAnalyzer** (`app/Services/Ai/ProductRiskAnalyzer.php`)
   - Core analysis engine
   - Performs deterministic checks on product data
   - Calculates risk scores based on well-defined rules
   - Integrates with Cohere for AI-enhanced recommendations
   - Falls back to basic recommendations if AI is unavailable

3. **ProductController** (`app/Http/Controllers/ProductController.php`)
   - Adds `analyzeRisk()` method for the analysis endpoint
   - Enforces authorization (users can only analyze their own products)

4. **Risk Analysis View** (`resources/views/products/risk-analysis.blade.php`)
   - Professional UI displaying analysis results
   - Color-coded risk indicators
   - Categorized issues with severity levels
   - AI-generated recommendations

### Data Flow

```
User clicks "Analyze Risk"
    ↓
ProductController::analyzeRisk()
    ↓
ProductRiskAnalyzer::analyze()
    ↓
1. Load product with relationships (lots, productions, certifications, etc.)
2. Perform deterministic checks (dates, quantities, completeness)
3. Calculate risk score
4. If Cohere configured → Request AI recommendations
5. Return structured analysis
    ↓
Display results in risk-analysis.blade.php
```

## Risk Assessment Rules

### 1. Traceability Completeness (max 65 points)

| Issue | Score |
|-------|-------|
| Missing product origin | +15 |
| Missing product description | +5 |
| Product in draft status | +10 |
| No lots associated | +20 |
| Lots without production records | +15 |
| No transport records | +10 |
| Distributed lots without distribution records | +15 |

### 2. Data Consistency (max 60+ points)

| Issue | Score |
|-------|-------|
| Expiration date before production date | +20 |
| Negative quantity | +15 |
| Current quantity > initial quantity | +10 |
| Transport arrival before departure | +20 |
| Transport before production | +20 |
| Transformation before input production | +20 |

### 3. Quality and Storage (max 50+ points)

| Issue | Score |
|-------|-------|
| Expired lot | +25 |
| Expires within 7 days | +10 |
| Missing expiration date (perishable product) | +10 |
| High pesticide use (>100 kg) | +5 |

### 4. Certification Verification (max 60+ points)

| Issue | Score |
|-------|-------|
| No certifications | +10 |
| Expired certifications | +15 per cert (max 3) |
| Pending certifications | +10 |
| Rejected certifications | +20 per cert (max 2) |
| Missing proof/certificate number | +5 per cert (max 3) |

### Risk Levels

- **Very Low**: Score 0-9
- **Low**: Score 10-29
- **Medium**: Score 30-59
- **High**: Score 60+

## Configuration

### 1. Environment Variables

Add to your `.env` file:

```env
# Cohere AI for Product Risk Analysis
COHERE_API_KEY=your_cohere_api_key_here
COHERE_MODEL=command-r-08-2024
COHERE_BASE_URL=https://api.cohere.com/v2
COHERE_TIMEOUT=30
```

### 2. Getting a Cohere API Key

1. Visit [https://dashboard.cohere.com/](https://dashboard.cohere.com/)
2. Sign up for a free account
3. Go to API Keys section
4. Create a new API key
5. Copy the key to your `.env` file

**Note**: Free trial keys are limited to 1,000 API calls per month. This is sufficient for development and small-scale use.

### 3. Supported Models

The feature uses `command-r-08-2024` by default, which is:
- Available on free trial accounts
- Optimized for multilingual content (French in this case)
- Good at structured reasoning tasks
- 128K context window

Alternative models you can use:
- `command-r-plus-08-2024` (more powerful, higher cost)
- `command-a` (faster, lower quality)

## Usage

### From the UI

1. Navigate to a product detail page
2. Click the **"Analyser les risques"** (Analyze Risks) button
3. View the comprehensive risk analysis report
4. Review recommendations and take action

### Programmatic Usage

```php
use App\Services\Ai\ProductRiskAnalyzer;
use App\Models\Product;

$analyzer = app(ProductRiskAnalyzer::class);
$product = Product::find($id);

$analysis = $analyzer->analyze($product);

// Access results
$riskLevel = $analysis['risk_level']; // 'low', 'medium', 'high', 'very_low'
$riskScore = $analysis['risk_score']; // 0-100+
$categories = $analysis['categories']; // Array of category checks
$recommendations = $analysis['recommendations']; // Array of strings
$aiExplanation = $analysis['ai_explanation']; // String or null
```

## Security

### API Key Protection

- ✅ API key is stored server-side in `.env`
- ✅ Never exposed to frontend JavaScript or HTML
- ✅ Not committed to Git (`.env` is in `.gitignore`)
- ✅ Configuration follows Laravel's service configuration pattern

### Data Privacy

- Only necessary product information is sent to Cohere
- No personal user data (names, emails, passwords) is transmitted
- Product data is minimized to what's needed for analysis
- Cohere's terms of service apply to transmitted data

### Authorization

- Users can only analyze their own products
- Authorization enforced via Laravel policies
- Admin users can analyze any product

## Testing

### Running Tests

```bash
# Run all risk analysis tests
php artisan test --filter=ProductRiskAnalysisTest

# Run unit tests for CohereClient
php artisan test --filter=CohereClientTest
```

### Test Coverage

The implementation includes comprehensive tests for:

- ✅ Authorization (producers can only analyze their own products)
- ✅ Admin access to all products
- ✅ Detection of missing origin, description, lots
- ✅ Detection of date inconsistencies
- ✅ Detection of expired lots and certifications
- ✅ Risk score calculation
- ✅ Graceful handling when Cohere is not configured
- ✅ Cohere API integration with mocked responses
- ✅ Error handling for API failures
- ✅ Fallback recommendations

### Mocking Cohere in Tests

Tests use Laravel's HTTP facade to mock Cohere responses:

```php
Http::fake([
    'api.cohere.com/*' => Http::response([
        'id' => 'test-id',
        'finish_reason' => 'COMPLETE',
        'message' => [
            'content' => [
                ['text' => 'AI recommendation text'],
            ],
        ],
    ], 200),
]);
```

## Error Handling

The implementation handles various failure scenarios gracefully:

### API Not Configured
- **Behavior**: Analysis proceeds with deterministic checks only
- **User Impact**: Basic recommendations provided, no AI explanation
- **Logging**: Warning logged when API key is missing

### API Connection Failure
- **Behavior**: Analysis completes with deterministic checks
- **User Impact**: Falls back to basic recommendations
- **Logging**: Connection errors logged with details

### API Rate Limit / 429 Errors
- **Behavior**: Retry logic attempts request again
- **User Impact**: May experience slight delay, or fall back to basic recommendations
- **Logging**: Rate limit errors logged

### Invalid API Response
- **Behavior**: Response validation catches malformed data
- **User Impact**: Falls back to basic recommendations
- **Logging**: Parsing errors logged with response data

### Timeout
- **Behavior**: Request times out after 30 seconds (configurable)
- **User Impact**: Analysis completes with deterministic checks only
- **Logging**: Timeout logged

## Performance Considerations

### Response Time

- Deterministic checks: ~100-300ms
- With Cohere API: ~1-3 seconds (depends on API response time)
- Total page load: ~1-4 seconds

### Optimization Strategies

1. **Eager Loading**: All relationships loaded upfront to minimize queries
2. **No Caching**: Analysis is real-time to ensure current data
3. **Timeout**: Set to 30 seconds to prevent long waits
4. **Retry Logic**: HTTP client retries failed requests up to 2 times

### Rate Limiting

- **Free Tier**: 1,000 API calls/month
- **Recommended**: Implement user-side throttling if needed
- **Current**: No throttling implemented (analysis on-demand)

## Limitations

### What This Feature Does NOT Do

❌ **Does not assess actual food safety**: This is a data quality tool, not a food safety inspection  
❌ **Does not modify data**: Only identifies issues, doesn't fix them  
❌ **Does not replace human judgment**: Recommendations require user review and action  
❌ **Does not verify external certifications**: Only checks if certification records are complete  
❌ **Does not guarantee traceability compliance**: Legal compliance requires expert review  

### Important Disclaimers

The analysis:
- Evaluates data completeness and consistency
- Identifies missing or inconsistent records
- Does NOT determine if a product is safe to consume
- Does NOT verify the authenticity of certifications
- Does NOT replace official food safety audits
- Should be used as a data quality improvement tool

## Troubleshooting

### "Cohere API not configured" in logs

**Cause**: `COHERE_API_KEY` is not set in `.env`  
**Solution**: Add your Cohere API key to the `.env` file

### Analysis shows no AI recommendations

**Possible Causes**:
1. API key not configured
2. API request failed (check logs)
3. Cohere service temporarily unavailable

**Solution**: Check `storage/logs/laravel.log` for detailed error messages

### "Rate limit exceeded" errors

**Cause**: Exceeded free tier limit (1,000 calls/month)  
**Solution**: 
- Wait for monthly reset
- Upgrade to paid Cohere plan
- Feature will still work with basic recommendations

### Tests failing with "could not find driver"

**Cause**: SQLite PDO extension not installed for tests  
**Solution**: 
- Install PHP SQLite extension
- Or run unit tests only: `php artisan test --filter=CohereClientTest`

## Future Enhancements

Possible improvements for future versions:

1. **Caching**: Cache analysis results for performance
2. **Historical Tracking**: Store analysis history over time
3. **Automated Alerts**: Email notifications for high-risk products
4. **Batch Analysis**: Analyze multiple products at once
5. **PDF Reports**: Export analysis to PDF
6. **Scheduled Analysis**: Automatic periodic analysis
7. **Comparison View**: Compare risk scores across products
8. **Custom Rules**: Allow users to configure scoring rules

## API Reference

### Cohere Chat Endpoint

**URL**: `https://api.cohere.com/v2/chat`  
**Method**: `POST`  
**Authentication**: Bearer token in Authorization header

**Request Body**:
```json
{
  "model": "command-r-08-2024",
  "messages": [
    {
      "role": "system",
      "content": "System instruction"
    },
    {
      "role": "user",
      "content": "User question"
    }
  ],
  "temperature": 0.3,
  "max_tokens": 2000
}
```

**Response**:
```json
{
  "id": "chat-id",
  "finish_reason": "COMPLETE",
  "message": {
    "content": [
      {
        "text": "Response text"
      }
    ]
  }
}
```

**Documentation**: [https://docs.cohere.com/docs/chat-api](https://docs.cohere.com/docs/chat-api)

## Support

For issues or questions:

1. Check `storage/logs/laravel.log` for detailed error messages
2. Review this documentation
3. Check Cohere API status: [https://status.cohere.com/](https://status.cohere.com/)
4. Review Cohere documentation: [https://docs.cohere.com/](https://docs.cohere.com/)

## Changelog

### Version 1.0.0 (Initial Release)

- ✅ Comprehensive risk analysis with 4 categories
- ✅ Integration with Cohere AI for recommendations
- ✅ Deterministic scoring rules
- ✅ Professional UI with risk visualization
- ✅ Full test coverage
- ✅ Graceful error handling
- ✅ French language support
- ✅ Security best practices implemented
